<?php

namespace App\Services\Refunds;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\RefundStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\ApiDomainException;
use App\Exceptions\RefundProviderException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundAttempt;
use App\Models\SubOrder;
use App\Models\SubOrderStatusHistory;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Finance\FinancialReconciliationService;
use App\Services\Notifications\NotificationOutboxService;
use App\Services\Refunds\Data\RefundExecutionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

final class RefundService
{
    public function __construct(
        private readonly RefundProviderManager $providers,
        private readonly FinancialReconciliationService $reconciliation,
        private readonly NotificationOutboxService $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{amount?: int|null, reason: string, idempotency_key: string}  $input
     *
     * @throws JsonException
     */
    public function request(
        User $actor,
        Order $order,
        array $input,
        Request $request,
    ): RefundAttempt {
        return DB::transaction(function () use ($actor, $order, $input, $request): RefundAttempt {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = PaymentAttempt::query()
                ->where('order_id', $lockedOrder->id)
                ->where('status', PaymentAttemptStatus::Verified->value)
                ->latest('verified_at')
                ->lockForUpdate()
                ->first();

            if (! $payment instanceof PaymentAttempt) {
                throw new ApiDomainException(
                    'refund.verified_payment_required',
                    'برای این سفارش پرداخت تأییدشده و قابل بازپرداخت وجود ندارد.',
                    409,
                );
            }

            $amount = (int) ($input['amount'] ?? $payment->amount);
            $reason = trim($input['reason']);
            $idempotencyKey = trim($input['idempotency_key']);
            $requestHash = hash('sha256', json_encode([
                'order_id' => $lockedOrder->id,
                'payment_attempt_id' => $payment->id,
                'amount' => $amount,
                'currency' => $payment->currency,
                'reason' => $reason,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $existing = RefundAttempt::query()
                ->where('order_id', $lockedOrder->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof RefundAttempt) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new ApiDomainException(
                        'refund.idempotency_conflict',
                        'این کلید Idempotency قبلاً برای درخواست بازپرداخت دیگری استفاده شده است.',
                        409,
                    );
                }

                return $existing;
            }

            $reservedAmount = (int) RefundAttempt::query()
                ->where('payment_attempt_id', $payment->id)
                ->whereIn('status', [
                    RefundStatus::Requested->value,
                    RefundStatus::Approved->value,
                    RefundStatus::Processing->value,
                    RefundStatus::Succeeded->value,
                    RefundStatus::RequiresReview->value,
                ])
                ->sum('amount');

            if ($amount < 1 || $amount > ($payment->amount - $reservedAmount)) {
                throw new ApiDomainException(
                    'refund.amount_exceeds_available',
                    'مبلغ بازپرداخت از مانده قابل بازپرداخت بیشتر است.',
                    409,
                    fields: [
                        'amount' => ['حداکثر مبلغ قابل بازپرداخت '.max(0, $payment->amount - $reservedAmount).' ریال است.'],
                    ],
                );
            }

            $provider = strtolower(trim((string) config('rosta.refund.provider', 'disabled')));
            $refund = RefundAttempt::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_attempt_id' => $payment->id,
                'requested_by' => $actor->id,
                'provider' => $provider,
                'status' => RefundStatus::Requested,
                'amount' => $amount,
                'currency' => $payment->currency,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'reason' => $reason,
                'request_payload' => [
                    'payment_reference_id' => $payment->reference_id,
                    'requested_amount' => $amount,
                ],
            ]);

            if ($lockedOrder->status !== OrderStatus::Refunded) {
                $lockedOrder->forceFill(['status' => OrderStatus::RefundPending])->save();
            }

            $this->audit->record(
                'refund.requested',
                actor: $actor,
                auditable: $refund,
                metadata: [
                    'order_id' => $lockedOrder->id,
                    'payment_attempt_id' => $payment->id,
                    'amount' => $amount,
                    'provider' => $provider,
                ],
                request: $request,
            );

            return $refund;
        }, 3);
    }

    public function approve(
        User $actor,
        RefundAttempt $refund,
        Request $request,
    ): RefundAttempt {
        return DB::transaction(function () use ($actor, $refund, $request): RefundAttempt {
            $locked = RefundAttempt::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === RefundStatus::Approved) {
                return $locked;
            }
            if ($locked->status !== RefundStatus::Requested) {
                throw new ApiDomainException(
                    'refund.approval_invalid_state',
                    'این درخواست در وضعیت قابل تأیید نیست.',
                    409,
                );
            }
            if (
                config('rosta.refund.require_dual_control', true)
                && $locked->requested_by === $actor->id
            ) {
                throw new ApiDomainException(
                    'refund.dual_control_required',
                    'ثبت‌کننده درخواست نمی‌تواند همان بازپرداخت را تأیید کند.',
                    409,
                );
            }

            $locked->forceFill([
                'status' => RefundStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            $this->audit->record(
                'refund.approved',
                actor: $actor,
                auditable: $locked,
                metadata: ['order_id' => $locked->order_id, 'amount' => $locked->amount],
                request: $request,
            );

            return $locked;
        }, 3);
    }

    public function dispatch(
        User $actor,
        RefundAttempt $refund,
        Request $request,
    ): RefundAttempt {
        if (! $this->providers->ready()) {
            throw new ApiDomainException(
                'refund.provider_unavailable',
                'ارائه‌دهنده بازپرداخت فعال یا آماده نیست.',
                503,
            );
        }

        $prepared = DB::transaction(function () use ($actor, $refund, $request): RefundAttempt {
            $locked = RefundAttempt::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [RefundStatus::Processing, RefundStatus::Succeeded], true)) {
                return $locked;
            }
            if ($locked->status !== RefundStatus::Approved) {
                throw new ApiDomainException(
                    'refund.dispatch_invalid_state',
                    'فقط بازپرداخت تأییدشده قابل ارسال است.',
                    409,
                );
            }

            $locked->forceFill([
                'status' => RefundStatus::Processing,
                'processing_at' => now(),
                'provider' => $this->providers->current()->name(),
            ])->save();

            $this->audit->record(
                'refund.dispatched',
                actor: $actor,
                auditable: $locked,
                metadata: ['order_id' => $locked->order_id, 'provider' => $locked->provider],
                request: $request,
            );

            return $locked;
        }, 3);

        if ($prepared->status === RefundStatus::Succeeded) {
            return $prepared;
        }

        $prepared->loadMissing(['order', 'paymentAttempt']);
        try {
            $result = $this->providers->for($prepared->provider)->execute(
                $prepared,
                $prepared->paymentAttempt,
                $prepared->order,
            );
        } catch (Throwable $exception) {
            return $this->markUnknownOutcome($actor, $prepared, $exception, $request);
        }

        return $this->applyProviderResult($actor, $prepared, $result, $request);
    }

    /**
     * @param  array{outcome: string, provider_reference?: string|null, failure_code?: string|null, message?: string|null}  $input
     */
    public function resolveManual(
        User $actor,
        RefundAttempt $refund,
        array $input,
        Request $request,
    ): RefundAttempt {
        return DB::transaction(function () use ($actor, $refund, $input, $request): RefundAttempt {
            $locked = RefundAttempt::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status->isTerminal()) {
                return $locked;
            }
            if (! in_array($locked->status, [RefundStatus::Processing, RefundStatus::RequiresReview], true)) {
                throw new ApiDomainException(
                    'refund.manual_resolution_invalid_state',
                    'این بازپرداخت در وضعیت قابل ثبت نتیجه نیست.',
                    409,
                );
            }

            return match ($input['outcome']) {
                'succeeded' => $this->completeSucceededLocked(
                    $actor,
                    $locked,
                    trim((string) ($input['provider_reference'] ?? '')),
                    'manual_resolution',
                    ['message' => $input['message'] ?? null],
                    $request,
                ),
                'failed' => $this->completeFailedLocked(
                    $actor,
                    $locked,
                    trim((string) ($input['failure_code'] ?? 'manual_failure')),
                    trim((string) ($input['message'] ?? 'بازپرداخت ناموفق ثبت شد.')),
                    $request,
                ),
                'cancelled' => $this->completeCancelledLocked($actor, $locked, $request),
                default => throw new ApiDomainException(
                    'refund.outcome_invalid',
                    'نتیجه بازپرداخت معتبر نیست.',
                    422,
                ),
            };
        }, 3);
    }

    private function applyProviderResult(
        User $actor,
        RefundAttempt $refund,
        RefundExecutionResult $result,
        Request $request,
    ): RefundAttempt {
        return DB::transaction(function () use ($actor, $refund, $result, $request): RefundAttempt {
            $locked = RefundAttempt::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status->isTerminal()) {
                return $locked;
            }

            return match ($result->state) {
                'pending' => tap($locked, function (RefundAttempt $attempt) use ($result): void {
                    $attempt->forceFill([
                        'provider_code' => $result->providerCode,
                        'response_payload' => $result->payload,
                    ])->save();
                }),
                'succeeded' => $this->completeSucceededLocked(
                    $actor,
                    $locked,
                    (string) $result->referenceId,
                    $result->providerCode,
                    $result->payload,
                    $request,
                ),
                'failed' => $this->completeFailedLocked(
                    $actor,
                    $locked,
                    $result->providerCode ?: 'provider_failed',
                    $result->message ?: 'بازپرداخت توسط ارائه‌دهنده رد شد.',
                    $request,
                    $result->payload,
                ),
                default => throw new ApiDomainException(
                    'refund.provider_result_invalid',
                    'پاسخ ارائه‌دهنده بازپرداخت معتبر نیست.',
                    503,
                ),
            };
        }, 3);
    }

    private function markUnknownOutcome(
        User $actor,
        RefundAttempt $refund,
        Throwable $exception,
        Request $request,
    ): RefundAttempt {
        return DB::transaction(function () use ($actor, $refund, $exception, $request): RefundAttempt {
            $locked = RefundAttempt::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status->isTerminal()) {
                return $locked;
            }

            $code = $exception instanceof RefundProviderException
                ? ($exception->providerCode ?: 'provider_unknown_outcome')
                : 'provider_unknown_outcome';
            $locked->forceFill([
                'status' => RefundStatus::RequiresReview,
                'failure_code' => $code,
                'failure_message' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
            $this->reconciliation->openRefundReview(
                $locked,
                'refund_unknown_outcome',
                'نتیجه بازپرداخت از ارائه‌دهنده قطعی نیست و باید تطبیق داده شود.',
                ['failure_code' => $code, 'provider' => $locked->provider],
                'critical',
                $actor,
            );
            $this->audit->record(
                'refund.requires_review',
                actor: $actor,
                auditable: $locked,
                metadata: ['order_id' => $locked->order_id, 'failure_code' => $code],
                request: $request,
            );

            return $locked;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function completeSucceededLocked(
        User $actor,
        RefundAttempt $refund,
        string $providerReference,
        ?string $providerCode,
        array $payload,
        Request $request,
    ): RefundAttempt {
        if ($providerReference === '') {
            throw new ApiDomainException(
                'refund.provider_reference_required',
                'شناسه مرجع بازپرداخت موفق الزامی است.',
                422,
            );
        }

        $refund->forceFill([
            'status' => RefundStatus::Succeeded,
            'resolved_by' => $actor->id,
            'provider_reference' => $providerReference,
            'provider_code' => $providerCode,
            'failure_code' => null,
            'failure_message' => null,
            'response_payload' => $payload,
            'succeeded_at' => now(),
        ])->save();

        $payment = PaymentAttempt::query()->whereKey($refund->payment_attempt_id)->lockForUpdate()->firstOrFail();
        $order = Order::query()->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
        $succeededAmount = (int) RefundAttempt::query()
            ->where('payment_attempt_id', $payment->id)
            ->where('status', RefundStatus::Succeeded->value)
            ->sum('amount');

        if ($succeededAmount >= $payment->amount) {
            $payment->forceFill(['status' => PaymentAttemptStatus::Refunded])->save();
            $order->forceFill([
                'status' => OrderStatus::Refunded,
                'refunded_at' => now(),
            ])->save();
            $subOrder = SubOrder::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($subOrder instanceof SubOrder && $subOrder->status !== SubOrderStatus::Refunded) {
                $from = $subOrder->status;
                $subOrder->forceFill(['status' => SubOrderStatus::Refunded])->save();
                SubOrderStatusHistory::query()->create([
                    'sub_order_id' => $subOrder->id,
                    'actor_id' => $actor->id,
                    'from_status' => $from->value,
                    'to_status' => SubOrderStatus::Refunded->value,
                    'reason' => 'refund_succeeded',
                    'metadata' => ['refund_attempt_id' => $refund->id],
                    'created_at' => now(),
                ]);
            }
            $this->outbox->queueOrder(
                $order,
                'order.refunded',
                ['refund_reference' => $providerReference],
                $subOrder,
                "order:{$order->id}:refunded",
            );
        } else {
            $order->forceFill(['status' => OrderStatus::RefundPending])->save();
            $this->reconciliation->openRefundReview(
                $refund,
                'partial_refund_balance_open',
                'بخشی از مبلغ پرداخت بازگردانده شده و مانده نیازمند تصمیم مالی است.',
                [
                    'paid_amount' => $payment->amount,
                    'succeeded_refund_amount' => $succeededAmount,
                    'remaining_amount' => max(0, $payment->amount - $succeededAmount),
                ],
                'high',
                $actor,
            );
        }

        $this->audit->record(
            'refund.succeeded',
            actor: $actor,
            auditable: $refund,
            metadata: [
                'order_id' => $order->id,
                'amount' => $refund->amount,
                'total_refunded' => $succeededAmount,
                'provider_reference' => $providerReference,
            ],
            request: $request,
        );

        return $refund;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function completeFailedLocked(
        User $actor,
        RefundAttempt $refund,
        string $failureCode,
        string $message,
        Request $request,
        array $payload = [],
    ): RefundAttempt {
        $refund->forceFill([
            'status' => RefundStatus::Failed,
            'resolved_by' => $actor->id,
            'failure_code' => $failureCode,
            'failure_message' => mb_substr($message, 0, 1000),
            'response_payload' => $payload,
            'failed_at' => now(),
        ])->save();
        $this->reconciliation->openRefundReview(
            $refund,
            'refund_failed',
            'بازپرداخت ناموفق است و مانده پرداخت باید بررسی شود.',
            ['failure_code' => $failureCode, 'provider' => $refund->provider],
            'high',
            $actor,
        );
        $this->audit->record(
            'refund.failed',
            actor: $actor,
            auditable: $refund,
            metadata: ['order_id' => $refund->order_id, 'failure_code' => $failureCode],
            request: $request,
        );

        return $refund;
    }

    private function completeCancelledLocked(
        User $actor,
        RefundAttempt $refund,
        Request $request,
    ): RefundAttempt {
        $refund->forceFill([
            'status' => RefundStatus::Cancelled,
            'resolved_by' => $actor->id,
            'cancelled_at' => now(),
        ])->save();
        $this->audit->record(
            'refund.cancelled',
            actor: $actor,
            auditable: $refund,
            metadata: ['order_id' => $refund->order_id],
            request: $request,
        );

        return $refund;
    }
}
