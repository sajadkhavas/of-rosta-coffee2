<?php

namespace App\Services\Settlement;

use App\Enums\SettlementAllocationStatus;
use App\Enums\SettlementBatchStatus;
use App\Exceptions\ApiDomainException;
use App\Models\Roastery;
use App\Models\SettlementAllocation;
use App\Models\SettlementBatch;
use App\Models\SettlementBatchAllocation;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Finance\FinancialReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;

final class SettlementBatchService
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly FinancialReconciliationService $reconciliation,
    ) {}

    public function create(
        User $actor,
        Roastery $roastery,
        string $currency,
        string $idempotencyKey,
        Request $request,
    ): SettlementBatch {
        return DB::transaction(function () use ($actor, $roastery, $currency, $idempotencyKey, $request): SettlementBatch {
            $existing = SettlementBatch::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof SettlementBatch) {
                if ($existing->roastery_id !== $roastery->id || $existing->currency !== $currency) {
                    throw new ApiDomainException('settlement.batch_idempotency_conflict', 'کلید تکرارپذیری با Batch دیگری استفاده شده است.', 409);
                }
                return $this->loadBatch($existing);
            }

            $allocations = SettlementAllocation::query()
                ->where('owner_type', 'roastery')->where('owner_id', $roastery->id)->where('currency', $currency)
                ->where('status', SettlementAllocationStatus::Eligible->value)
                ->whereNotExists(static function ($query): void {
                    $query->selectRaw('1')->from('settlement_batch_allocations')->whereColumn(
                        'settlement_batch_allocations.settlement_allocation_id', 'settlement_allocations.id'
                    );
                })
                ->orderBy('eligible_at')->orderBy('id')->lockForUpdate()->get();
            if ($allocations->isEmpty()) {
                throw new ApiDomainException('settlement.no_eligible_allocations', 'Allocation قابل‌تسویه‌ای برای این روستری وجود ندارد.', 409);
            }

            $scheduledAt = now();
            $batch = SettlementBatch::query()->create([
                'roastery_id' => $roastery->id,
                'created_by_id' => $actor->id,
                'status' => SettlementBatchStatus::Pending,
                'currency' => $currency,
                'gross_total' => $allocations->sum('gross_amount'),
                'discount_total' => $allocations->sum('discount_amount'),
                'tax_total' => $allocations->sum('tax_amount'),
                'net_total' => $allocations->sum('net_amount'),
                'allocation_count' => $allocations->count(),
                'idempotency_key' => $idempotencyKey,
                'scheduled_at' => $scheduledAt,
            ]);
            foreach ($allocations as $allocation) {
                SettlementBatchAllocation::query()->create([
                    'settlement_batch_id' => $batch->id,
                    'settlement_allocation_id' => $allocation->id,
                    'gross_amount' => $allocation->gross_amount,
                    'discount_amount' => $allocation->discount_amount,
                    'tax_amount' => $allocation->tax_amount,
                    'net_amount' => $allocation->net_amount,
                    'attached_at' => $scheduledAt,
                ]);
                $allocation->forceFill(['status' => SettlementAllocationStatus::Scheduled, 'scheduled_at' => $scheduledAt])->save();
            }
            $this->audit->record(
                'settlement.batch_created', actor: $actor, auditable: $batch,
                metadata: ['roastery_id' => $roastery->id, 'allocation_count' => $batch->allocation_count, 'net_total' => $batch->net_total, 'currency' => $currency],
                request: $request,
            );
            return $this->loadBatch($batch);
        }, 3);
    }

    /**
     * @param array{action:string,payout_reference?:string|null,payout_method?:string|null,payout_evidence?:array{source:string,executed_at:string,amount:int,currency:string,note?:string|null}|null,failure_code?:string|null,failure_message?:string|null} $input
     * @throws JsonException
     */
    public function resolve(User $actor, SettlementBatch $batch, array $input, Request $request): SettlementBatch
    {
        return DB::transaction(function () use ($actor, $batch, $input, $request): SettlementBatch {
            $locked = SettlementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $action = $input['action'];
            $now = now();

            if ($action === 'process') {
                if ($locked->status === SettlementBatchStatus::Processing) {
                    return $this->loadBatch($locked);
                }
                if (! in_array($locked->status, [SettlementBatchStatus::Pending, SettlementBatchStatus::Failed], true)) {
                    throw new ApiDomainException('settlement.batch_not_processable', 'این Batch قابل پردازش نیست.', 409);
                }
                $locked->forceFill([
                    'status' => SettlementBatchStatus::Processing,
                    'processed_by_id' => $actor->id,
                    'confirmed_by_id' => null,
                    'processing_at' => $now,
                    'failure_code' => null,
                    'failure_message' => null,
                    'failed_at' => null,
                ])->save();
            } elseif ($action === 'paid') {
                $reference = trim((string) ($input['payout_reference'] ?? ''));
                $method = trim((string) ($input['payout_method'] ?? ''));
                $evidence = $this->normalizePayoutEvidence($locked, $input['payout_evidence'] ?? null);
                $evidenceHash = hash('sha256', json_encode([
                    'payout_reference' => $reference,
                    'payout_method' => $method,
                    'payout_evidence' => $evidence,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                if ($locked->status === SettlementBatchStatus::Paid) {
                    if (
                        hash_equals((string) $locked->payout_reference, $reference)
                        && hash_equals((string) $locked->payout_method, $method)
                        && hash_equals((string) $locked->payout_evidence_hash, $evidenceHash)
                    ) {
                        return $this->loadBatch($locked);
                    }
                    throw new ApiDomainException('settlement.payout_idempotency_conflict', 'این Batch قبلاً با مدرک پرداخت دیگری بسته شده است.', 409);
                }
                if (! in_array($locked->status, [SettlementBatchStatus::Processing, SettlementBatchStatus::RequiresReview], true)) {
                    throw new ApiDomainException('settlement.batch_not_processing', 'Batch ابتدا باید در حال پردازش یا بررسی مالی باشد.', 409);
                }
                if ($reference === '' || $method === '') {
                    throw new ApiDomainException('settlement.payout_evidence_required', 'مرجع، روش و مدرک پرداخت الزامی است.', 422);
                }
                if (config('rosta.settlement.require_payout_dual_control', true) && $locked->processed_by_id === $actor->id) {
                    throw new ApiDomainException('settlement.payout_dual_control_required', 'ثبت‌کننده پردازش نمی‌تواند همان پرداخت را نهایی کند.', 409);
                }
                if (SettlementBatch::query()->where('payout_reference', $reference)->where('id', '!=', $locked->id)->exists()) {
                    throw new ApiDomainException('settlement.payout_reference_conflict', 'این مرجع پرداخت قبلاً ثبت شده است.', 409);
                }

                $locked->forceFill([
                    'status' => SettlementBatchStatus::Paid,
                    'confirmed_by_id' => $actor->id,
                    'payout_reference' => $reference,
                    'payout_method' => $method,
                    'payout_evidence_hash' => $evidenceHash,
                    'payout_evidence' => $evidence,
                    'failure_code' => null,
                    'failure_message' => null,
                    'failed_at' => null,
                    'paid_at' => $now,
                ])->save();
                SettlementAllocation::query()
                    ->whereIn('id', SettlementBatchAllocation::query()->where('settlement_batch_id', $locked->id)->select('settlement_allocation_id'))
                    ->where('status', SettlementAllocationStatus::Scheduled->value)
                    ->update(['status' => SettlementAllocationStatus::Paid->value, 'paid_at' => $now]);
            } elseif ($action === 'review') {
                if ($locked->status === SettlementBatchStatus::RequiresReview) {
                    return $this->loadBatch($locked);
                }
                if ($locked->status !== SettlementBatchStatus::Processing) {
                    throw new ApiDomainException('settlement.batch_not_processing', 'فقط Batch در حال پردازش قابل انتقال به بررسی مالی است.', 409);
                }
                $failureCode = trim((string) ($input['failure_code'] ?? 'payout_unknown_outcome'));
                $failureMessage = $this->nullableTrim($input['failure_message'] ?? null);
                $locked->forceFill([
                    'status' => SettlementBatchStatus::RequiresReview,
                    'failure_code' => $failureCode,
                    'failure_message' => $failureMessage,
                    'failed_at' => null,
                ])->save();
                $this->reconciliation->openSettlementBatchReview(
                    $locked,
                    'settlement_payout_unknown_outcome',
                    'نتیجه انتقال وجه قطعی نیست و پیش از هر تلاش مجدد باید تطبیق مالی شود.',
                    ['failure_code' => $failureCode, 'failure_message' => $failureMessage],
                    'critical',
                    $actor,
                );
            } else {
                if ($locked->status === SettlementBatchStatus::Failed) {
                    return $this->loadBatch($locked);
                }
                if (! in_array($locked->status, [SettlementBatchStatus::Processing, SettlementBatchStatus::RequiresReview], true)) {
                    throw new ApiDomainException('settlement.batch_not_processing', 'Batch ابتدا باید در حال پردازش یا بررسی مالی باشد.', 409);
                }
                $failureCode = trim((string) ($input['failure_code'] ?? 'payout_failed'));
                $failureMessage = $this->nullableTrim($input['failure_message'] ?? null);
                $locked->forceFill([
                    'status' => SettlementBatchStatus::Failed,
                    'failure_code' => $failureCode,
                    'failure_message' => $failureMessage,
                    'failed_at' => $now,
                ])->save();
                $this->reconciliation->openSettlementBatchReview(
                    $locked,
                    'settlement_payout_failed',
                    'پرداخت تسویه ناموفق ثبت شده و مانده باید تطبیق داده شود.',
                    ['failure_code' => $failureCode, 'failure_message' => $failureMessage],
                    'high',
                    $actor,
                );
            }

            $this->audit->record(
                'settlement.batch_'.$action,
                actor: $actor,
                auditable: $locked,
                metadata: [
                    'status' => $locked->status->value,
                    'roastery_id' => $locked->roastery_id,
                    'allocation_count' => $locked->allocation_count,
                    'net_total' => $locked->net_total,
                    'payout_reference' => $locked->payout_reference,
                    'payout_method' => $locked->payout_method,
                    'payout_evidence_hash' => $locked->payout_evidence_hash,
                    'failure_code' => $locked->failure_code,
                ],
                request: $request,
            );
            return $this->loadBatch($locked->refresh());
        }, 3);
    }

    /** @param array{source:string,executed_at:string,amount:int,currency:string,note?:string|null}|null $evidence */
    private function normalizePayoutEvidence(SettlementBatch $batch, ?array $evidence): array
    {
        if ($evidence === null) {
            throw new ApiDomainException('settlement.payout_evidence_required', 'مدرک پرداخت برای نهایی‌سازی تسویه الزامی است.', 422);
        }
        $amount = (int) ($evidence['amount'] ?? 0);
        $currency = strtoupper(trim((string) ($evidence['currency'] ?? '')));
        if ($amount !== $batch->net_total || $currency !== $batch->currency) {
            throw new ApiDomainException('settlement.payout_evidence_amount_mismatch', 'مبلغ و ارز مدرک پرداخت باید دقیقاً با Batch برابر باشد.', 409);
        }

        return [
            'source' => trim((string) ($evidence['source'] ?? '')),
            'executed_at' => CarbonImmutable::parse((string) ($evidence['executed_at'] ?? ''))->utc()->toIso8601String(),
            'amount' => $amount,
            'currency' => $currency,
            'note' => $this->nullableTrim($evidence['note'] ?? null),
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    public function loadBatch(SettlementBatch $batch): SettlementBatch
    {
        return $batch->load(['roastery', 'creator', 'processor', 'confirmer', 'allocations']);
    }
}
