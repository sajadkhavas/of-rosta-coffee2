<?php

namespace App\Services\Finance;

use App\Enums\ReconciliationStatus;
use App\Exceptions\ApiDomainException;
use App\Models\FinancialReconciliationCase;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundAttempt;
use App\Models\SettlementAllocation;
use App\Models\SettlementBatch;
use App\Models\SettlementBatchAllocation;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FinancialReconciliationService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $details */
    public function openPaymentReview(PaymentAttempt $payment, string $kind, string $summary, array $details = [], string $severity = 'critical', ?User $actor = null): FinancialReconciliationCase
    {
        return $this->open($payment->order()->firstOrFail(), $kind, $summary, $details, $severity, $actor, $payment);
    }

    /** @param array<string, mixed> $details */
    public function openRefundReview(RefundAttempt $refund, string $kind, string $summary, array $details = [], string $severity = 'high', ?User $actor = null): FinancialReconciliationCase
    {
        return $this->open(
            $refund->order()->firstOrFail(),
            $kind,
            $summary,
            $details,
            $severity,
            $actor,
            $refund->paymentAttempt()->first(),
            $refund,
        );
    }

    /**
     * A settlement batch can span several orders. Open one deduplicated case per affected order
     * so the existing reconciliation ownership/invariants stay intact.
     *
     * @param array<string, mixed> $details
     * @return list<FinancialReconciliationCase>
     */
    public function openSettlementBatchReview(SettlementBatch $batch, string $kind, string $summary, array $details = [], string $severity = 'critical', ?User $actor = null): array
    {
        $allocationIds = SettlementBatchAllocation::query()
            ->where('settlement_batch_id', $batch->id)
            ->pluck('settlement_allocation_id');
        $orderIds = SettlementAllocation::query()
            ->whereIn('id', $allocationIds)
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->unique()
            ->values();

        $cases = [];
        foreach ($orderIds as $orderId) {
            $order = Order::query()->findOrFail($orderId);
            $cases[] = $this->open(
                $order,
                $kind,
                $summary,
                ['settlement_batch_id' => $batch->id, ...$details],
                $severity,
                $actor,
                deduplicationSubject: 'settlement:'.$batch->id,
            );
        }

        return $cases;
    }

    public function resolve(User $actor, FinancialReconciliationCase $case, ReconciliationStatus $status, string $resolution, Request $request): FinancialReconciliationCase
    {
        if (! $status->isTerminal()) {
            throw new ApiDomainException('finance.reconciliation_terminal_status_required', 'برای بستن پرونده باید وضعیت resolved یا dismissed انتخاب شود.', 422);
        }

        return DB::transaction(function () use ($actor, $case, $status, $resolution, $request): FinancialReconciliationCase {
            $locked = FinancialReconciliationCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            if ($locked->status->isTerminal()) {
                return $locked;
            }

            $locked->forceFill([
                'status' => $status,
                'resolution' => $resolution,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ])->save();

            $this->audit->record(
                'finance.reconciliation_resolved',
                actor: $actor,
                auditable: $locked,
                metadata: ['order_id' => $locked->order_id, 'kind' => $locked->kind, 'status' => $status->value],
                request: $request,
            );

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $details */
    private function open(
        Order $order,
        string $kind,
        string $summary,
        array $details,
        string $severity,
        ?User $actor = null,
        ?PaymentAttempt $payment = null,
        ?RefundAttempt $refund = null,
        ?string $deduplicationSubject = null,
    ): FinancialReconciliationCase {
        $safeKind = mb_substr(trim($kind), 0, 80);
        if ($safeKind === '') {
            $safeKind = 'financial_review_required';
        }
        $parts = [
            $safeKind,
            $order->id,
            $payment instanceof PaymentAttempt ? $payment->id : '-',
            $refund instanceof RefundAttempt ? $refund->id : '-',
        ];
        if ($deduplicationSubject !== null) {
            $parts[] = $deduplicationSubject;
        }
        $deduplicationKey = hash('sha256', implode('|', $parts));

        return FinancialReconciliationCase::query()->createOrFirst(
            ['deduplication_key' => $deduplicationKey],
            [
                'order_id' => $order->id,
                'payment_attempt_id' => $payment?->id,
                'refund_attempt_id' => $refund?->id,
                'opened_by' => $actor?->id,
                'kind' => $safeKind,
                'status' => ReconciliationStatus::Open,
                'severity' => in_array($severity, ['low', 'medium', 'high', 'critical'], true) ? $severity : 'high',
                'summary' => mb_substr($summary, 0, 1000),
                'details' => $details,
                'opened_at' => now(),
            ],
        );
    }
}
