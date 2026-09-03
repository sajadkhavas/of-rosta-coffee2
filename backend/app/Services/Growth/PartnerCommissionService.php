<?php

namespace App\Services\Growth;

use App\Enums\RefundStatus;
use App\Exceptions\ApiDomainException;
use App\Models\GrowthPartner;
use App\Models\Order;
use App\Models\PartnerAttribution;
use App\Models\PartnerCommissionEntry;
use App\Models\PartnerCommissionPolicy;
use App\Models\RefundAttempt;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Finance\MoneyMath;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PartnerCommissionService
{
    private const SOURCE_ORDER_PAYMENT = 'order_payment';

    private const SOURCE_REFUND_ATTEMPT = 'refund_attempt';

    public function __construct(
        private readonly MoneyMath $money,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function accrueForPaidOrder(Order $order, ?User $actor = null): ?PartnerCommissionEntry
    {
        $sourceId = (string) $order->id;

        try {
            return DB::transaction(function () use ($order, $actor, $sourceId): ?PartnerCommissionEntry {
                $lockedOrder = Order::query()->lockForUpdate()->find($order->id);
                if (! $lockedOrder instanceof Order) {
                    throw new ApiDomainException('growth.order_not_found', 'سفارش برای محاسبه کمیسیون پیدا نشد.', 404);
                }

                if ($lockedOrder->paid_at === null) {
                    throw new ApiDomainException(
                        'growth.order_not_paid',
                        'کمیسیون همکار رشد فقط پس از پرداخت قطعی قابل ثبت است.',
                        409,
                    );
                }

                $existing = PartnerCommissionEntry::query()
                    ->where('source_type', self::SOURCE_ORDER_PAYMENT)
                    ->where('source_id', $sourceId)
                    ->lockForUpdate()
                    ->first();
                if ($existing instanceof PartnerCommissionEntry) {
                    return $existing;
                }

                $attribution = $this->resolveOrderAttribution($lockedOrder);
                if (! $attribution instanceof PartnerAttribution) {
                    return null;
                }

                $partner = GrowthPartner::query()->lockForUpdate()->find($attribution->partner_id);
                if (! $partner instanceof GrowthPartner || ! $partner->isActive()) {
                    throw new ApiDomainException(
                        'growth.partner_not_active',
                        'همکار رشد منتسب به سفارش در زمان ثبت کمیسیون فعال نیست.',
                        409,
                    );
                }

                if ($lockedOrder->user_id !== null && hash_equals((string) $partner->user_id, (string) $lockedOrder->user_id)) {
                    throw new ApiDomainException(
                        'growth.self_referral_forbidden',
                        'برای سفارش متعلق به خود همکار رشد کمیسیون ثبت نمی‌شود.',
                        409,
                    );
                }

                $financialSnapshot = $lockedOrder->financial_snapshot;
                $financialGroups = $financialSnapshot['groups'] ?? null;
                $hasAuthoritativeTruth = ($financialSnapshot['version'] ?? null) === 'ps4a-financial-truth-v1'
                    && is_array($financialGroups)
                    && $financialGroups !== [];
                if ($hasAuthoritativeTruth) {
                    foreach ($financialGroups as $financialGroup) {
                        if (
                            ! is_array($financialGroup)
                            || (($financialGroup['snapshot']['status'] ?? null) !== 'authoritative')
                        ) {
                            $hasAuthoritativeTruth = false;
                            break;
                        }
                    }
                }
                if (! $hasAuthoritativeTruth) {
                    throw new ApiDomainException(
                        'growth.financial_truth_required',
                        'کمیسیون فقط از Financial Truth قطعی قابل محاسبه است.',
                        503,
                    );
                }

                $paidAt = $lockedOrder->paid_at;
                $policy = $this->resolvePolicy($paidAt);
                $platformRevenue = (int) $lockedOrder->commission_total;
                $attributedGmv = (int) $lockedOrder->subtotal - (int) $lockedOrder->discount_total;
                if ($platformRevenue < 0 || $attributedGmv < 0 || (int) $lockedOrder->grand_total <= 0) {
                    throw new ApiDomainException(
                        'growth.financial_truth_invalid',
                        'مقادیر Financial Truth سفارش برای کمیسیون معتبر نیست.',
                        409,
                    );
                }

                $commission = $this->money->basisPoints(
                    $platformRevenue,
                    (int) $policy->basis_points,
                    (string) $policy->rounding_mode,
                );

                $entry = PartnerCommissionEntry::query()->create([
                    'partner_id' => $partner->id,
                    'attribution_id' => $attribution->id,
                    'policy_id' => $policy->id,
                    'order_id' => $lockedOrder->id,
                    'entry_type' => PartnerCommissionEntry::TYPE_ACCRUAL,
                    'status' => PartnerCommissionEntry::STATUS_PENDING,
                    'attributed_gmv_amount' => $attributedGmv,
                    'platform_revenue_amount' => $platformRevenue,
                    'commission_amount' => $commission,
                    'currency' => $lockedOrder->currency ?: 'IRR',
                    'idempotency_key' => 'partner-commission:order:'.$lockedOrder->id.':payment',
                    'source_type' => self::SOURCE_ORDER_PAYMENT,
                    'source_id' => $sourceId,
                    'financial_snapshot' => [
                        'order_financial_truth' => $financialSnapshot,
                        'order_subtotal' => (int) $lockedOrder->subtotal,
                        'order_discount_total' => (int) $lockedOrder->discount_total,
                        'order_grand_total' => (int) $lockedOrder->grand_total,
                        'platform_revenue_amount' => $platformRevenue,
                        'attributed_gmv_amount' => $attributedGmv,
                        'attributed_gmv_definition' => 'order_subtotal_minus_discount',
                        'partner_policy' => $this->policySnapshot($policy),
                    ],
                    'recorded_at' => now(),
                ]);

                if ($attribution->locked_at === null || $attribution->converted_at === null) {
                    $attribution->forceFill([
                        'locked_at' => $attribution->locked_at ?? now(),
                        'converted_at' => $attribution->converted_at ?? now(),
                    ])->save();
                }

                $this->auditRecorder->record(
                    action: 'growth.commission.accrued',
                    actor: $actor,
                    auditable: $entry,
                    metadata: [
                        'partner_id' => (string) $partner->id,
                        'order_id' => (string) $lockedOrder->id,
                        'attribution_id' => (string) $attribution->id,
                        'policy_version' => (string) $policy->version,
                        'commission_amount' => $commission,
                        'currency' => (string) ($lockedOrder->currency ?: 'IRR'),
                    ],
                );

                return $entry;
            });
        } catch (QueryException $exception) {
            $existing = PartnerCommissionEntry::query()
                ->where('source_type', self::SOURCE_ORDER_PAYMENT)
                ->where('source_id', $sourceId)
                ->first();
            if ($existing instanceof PartnerCommissionEntry) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function reverseForSuccessfulRefund(RefundAttempt $refund, ?User $actor = null): ?PartnerCommissionEntry
    {
        $sourceId = (string) $refund->id;

        try {
            return DB::transaction(function () use ($refund, $actor, $sourceId): ?PartnerCommissionEntry {
                $lockedRefund = RefundAttempt::query()->lockForUpdate()->find($refund->id);
                if (! $lockedRefund instanceof RefundAttempt) {
                    throw new ApiDomainException('growth.refund_not_found', 'Refund برای برگشت کمیسیون پیدا نشد.', 404);
                }

                if ($lockedRefund->status !== RefundStatus::Succeeded || $lockedRefund->succeeded_at === null) {
                    throw new ApiDomainException(
                        'growth.refund_not_succeeded',
                        'برگشت کمیسیون فقط برای Refund موفق مجاز است.',
                        409,
                    );
                }

                $existing = PartnerCommissionEntry::query()
                    ->where('source_type', self::SOURCE_REFUND_ATTEMPT)
                    ->where('source_id', $sourceId)
                    ->lockForUpdate()
                    ->first();
                if ($existing instanceof PartnerCommissionEntry) {
                    return $existing;
                }

                $order = Order::query()->lockForUpdate()->find($lockedRefund->order_id);
                if (! $order instanceof Order) {
                    throw new ApiDomainException('growth.order_not_found', 'سفارش Refund پیدا نشد.', 404);
                }

                $accrual = PartnerCommissionEntry::query()
                    ->where('order_id', $order->id)
                    ->where('entry_type', PartnerCommissionEntry::TYPE_ACCRUAL)
                    ->lockForUpdate()
                    ->first();
                if (! $accrual instanceof PartnerCommissionEntry) {
                    return null;
                }

                $orderTotal = (int) $order->grand_total;
                if ($orderTotal <= 0 || (int) $lockedRefund->amount <= 0) {
                    throw new ApiDomainException(
                        'growth.refund_amount_invalid',
                        'مبلغ Refund برای برگشت کمیسیون معتبر نیست.',
                        409,
                    );
                }

                $cumulativeRefund = (int) RefundAttempt::query()
                    ->where('order_id', $order->id)
                    ->where('status', RefundStatus::Succeeded->value)
                    ->sum('amount');
                if ($cumulativeRefund > $orderTotal) {
                    throw new ApiDomainException(
                        'growth.refund_over_order_total',
                        'جمع Refund موفق از مبلغ سفارش بیشتر است.',
                        409,
                    );
                }

                $weights = [
                    'refunded' => $cumulativeRefund,
                    'remaining' => $orderTotal - $cumulativeRefund,
                ];
                $targetCommissionReversed = $this->money->allocate((int) $accrual->commission_amount, $weights)['refunded'];
                $targetRevenueReversed = $this->money->allocate((int) $accrual->platform_revenue_amount, $weights)['refunded'];
                $targetGmvReversed = $this->money->allocate((int) $accrual->attributed_gmv_amount, $weights)['refunded'];

                $previousCommissionReversed = abs((int) PartnerCommissionEntry::query()
                    ->where('reversal_of_id', $accrual->id)
                    ->sum('commission_amount'));
                $previousRevenueReversed = (int) PartnerCommissionEntry::query()
                    ->where('reversal_of_id', $accrual->id)
                    ->sum('platform_revenue_amount');
                $previousGmvReversed = (int) PartnerCommissionEntry::query()
                    ->where('reversal_of_id', $accrual->id)
                    ->sum('attributed_gmv_amount');

                $commissionDelta = max(0, $targetCommissionReversed - $previousCommissionReversed);
                $revenueDelta = max(0, $targetRevenueReversed - $previousRevenueReversed);
                $gmvDelta = max(0, $targetGmvReversed - $previousGmvReversed);

                $entry = PartnerCommissionEntry::query()->create([
                    'partner_id' => $accrual->partner_id,
                    'attribution_id' => $accrual->attribution_id,
                    'policy_id' => $accrual->policy_id,
                    'order_id' => $order->id,
                    'entry_type' => PartnerCommissionEntry::TYPE_REVERSAL,
                    'reversal_of_id' => $accrual->id,
                    'status' => PartnerCommissionEntry::STATUS_REVERSED,
                    'attributed_gmv_amount' => $gmvDelta,
                    'platform_revenue_amount' => $revenueDelta,
                    'commission_amount' => -$commissionDelta,
                    'currency' => $accrual->currency,
                    'idempotency_key' => 'partner-commission:refund:'.$lockedRefund->id,
                    'source_type' => self::SOURCE_REFUND_ATTEMPT,
                    'source_id' => $sourceId,
                    'financial_snapshot' => [
                        'original_accrual_id' => (string) $accrual->id,
                        'refund_id' => (string) $lockedRefund->id,
                        'refund_amount' => (int) $lockedRefund->amount,
                        'cumulative_successful_refund_amount' => $cumulativeRefund,
                        'order_grand_total' => $orderTotal,
                        'allocation_method' => 'cumulative_largest_remainder',
                        'target_commission_reversed' => $targetCommissionReversed,
                        'previous_commission_reversed' => $previousCommissionReversed,
                    ],
                    'recorded_at' => now(),
                    'reason' => 'Successful refund commission reversal',
                ]);

                $this->auditRecorder->record(
                    action: 'growth.commission.reversed',
                    actor: $actor,
                    auditable: $entry,
                    metadata: [
                        'partner_id' => (string) $accrual->partner_id,
                        'order_id' => (string) $order->id,
                        'refund_id' => (string) $lockedRefund->id,
                        'original_accrual_id' => (string) $accrual->id,
                        'commission_amount' => -$commissionDelta,
                        'currency' => (string) $accrual->currency,
                    ],
                );

                return $entry;
            });
        } catch (QueryException $exception) {
            $existing = PartnerCommissionEntry::query()
                ->where('source_type', self::SOURCE_REFUND_ATTEMPT)
                ->where('source_id', $sourceId)
                ->first();
            if ($existing instanceof PartnerCommissionEntry) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function resolveOrderAttribution(Order $order): ?PartnerAttribution
    {
        $direct = PartnerAttribution::query()
            ->where('subject_type', 'order')
            ->where('subject_id', $order->id)
            ->lockForUpdate()
            ->first();
        if ($direct instanceof PartnerAttribution) {
            return $direct;
        }

        if ($order->user_id === null) {
            return null;
        }

        return PartnerAttribution::query()
            ->where('subject_type', 'user')
            ->where('subject_id', $order->user_id)
            ->lockForUpdate()
            ->first();
    }

    private function resolvePolicy(CarbonInterface $at): PartnerCommissionPolicy
    {
        $policy = PartnerCommissionPolicy::query()
            ->where('status', PartnerCommissionPolicy::STATUS_PUBLISHED)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->lockForUpdate()
            ->first();

        if (! $policy instanceof PartnerCommissionPolicy) {
            throw new ApiDomainException(
                'growth.commission_policy_missing',
                'Partner commission policy منتشرشده و مؤثر وجود ندارد.',
                503,
            );
        }

        if (
            $policy->basis !== PartnerCommissionPolicy::BASIS_PLATFORM_REVENUE
            || $policy->basis_points > MoneyMath::BASIS_POINTS
            || ! in_array($policy->rounding_mode, ['floor', 'half_up'], true)
            || ! is_string($policy->checksum)
            || strlen($policy->checksum) !== 64
        ) {
            throw new ApiDomainException(
                'growth.commission_policy_invalid',
                'Partner commission policy منتشرشده کامل و معتبر نیست.',
                503,
            );
        }

        return $policy;
    }

    /** @return array<string,mixed> */
    private function policySnapshot(PartnerCommissionPolicy $policy): array
    {
        return [
            'id' => (string) $policy->id,
            'version' => (string) $policy->version,
            'checksum' => (string) $policy->checksum,
            'basis' => (string) $policy->basis,
            'basis_points' => (int) $policy->basis_points,
            'rounding_mode' => (string) $policy->rounding_mode,
            'effective_from' => $policy->effective_from->toIso8601String(),
            'effective_to' => $policy->effective_to?->toIso8601String(),
        ];
    }
}
