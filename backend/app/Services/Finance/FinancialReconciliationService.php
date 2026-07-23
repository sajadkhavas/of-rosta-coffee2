<?php

namespace App\Services\Finance;

use App\Enums\ReconciliationStatus;
use App\Exceptions\ApiDomainException;
use App\Models\FinancialReconciliationCase;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundAttempt;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FinancialReconciliationService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function openPaymentReview(
        PaymentAttempt $payment,
        string $kind,
        string $summary,
        array $details = [],
        string $severity = 'critical',
        ?User $actor = null,
    ): FinancialReconciliationCase {
        return $this->open(
            order: $payment->order()->firstOrFail(),
            kind: $kind,
            summary: $summary,
            details: $details,
            severity: $severity,
            actor: $actor,
            payment: $payment,
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function openRefundReview(
        RefundAttempt $refund,
        string $kind,
        string $summary,
        array $details = [],
        string $severity = 'high',
        ?User $actor = null,
    ): FinancialReconciliationCase {
        return $this->open(
            order: $refund->order()->firstOrFail(),
            kind: $kind,
            summary: $summary,
            details: $details,
            severity: $severity,
            actor: $actor,
            payment: $refund->paymentAttempt()->first(),
            refund: $refund,
        );
    }

    public function resolve(
        User $actor,
        FinancialReconciliationCase $case,
        ReconciliationStatus $status,
        string $resolution,
        Request $request,
    ): FinancialReconciliationCase {
        if (! $status->isTerminal()) {
            throw new ApiDomainException(
                'finance.reconciliation_terminal_status_required',
                'برای بستن پرونده باید وضعیت resolved یا dismissed انتخاب شود.',
                422,
            );
        }

        return DB::transaction(function () use ($actor, $case, $status, $resolution, $request): FinancialReconciliationCase {
            $locked = FinancialReconciliationCase::query()
                ->whereKey($case->id)
                ->lockForUpdate()
                ->firstOrFail();

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
                metadata: [
                    'order_id' => $locked->order_id,
                    'kind' => $locked->kind,
                    'status' => $status->value,
                ],
                request: $request,
            );

            return $locked;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function open(
        Order $order,
        string $kind,
        string $summary,
        array $details,
        string $severity,
        ?User $actor = null,
        ?PaymentAttempt $payment = null,
        ?RefundAttempt $refund = null,
    ): FinancialReconciliationCase {
        $safeKind = mb_substr(trim($kind), 0, 80);
        if ($safeKind === '') {
            $safeKind = 'financial_review_required';
        }
        $deduplicationKey = hash('sha256', implode('|', [
            $safeKind,
            $order->id,
            $payment?->id ?? '-',
            $refund?->id ?? '-',
        ]));

        return FinancialReconciliationCase::query()->createOrFirst(
            ['deduplication_key' => $deduplicationKey],
            [
                'order_id' => $order->id,
                'payment_attempt_id' => $payment?->id,
                'refund_attempt_id' => $refund?->id,
                'opened_by' => $actor?->id,
                'kind' => $safeKind,
                'status' => ReconciliationStatus::Open,
                'severity' => in_array($severity, ['low', 'medium', 'high', 'critical'], true)
                    ? $severity
                    : 'high',
                'summary' => mb_substr($summary, 0, 1000),
                'details' => $details,
                'opened_at' => now(),
            ],
        );
    }
}
