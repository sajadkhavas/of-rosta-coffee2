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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SettlementBatchService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function create(
        User $actor,
        Roastery $roastery,
        string $currency,
        string $idempotencyKey,
        Request $request,
    ): SettlementBatch {
        return DB::transaction(function () use ($actor, $roastery, $currency, $idempotencyKey, $request): SettlementBatch {
            $existing = SettlementBatch::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof SettlementBatch) {
                if ($existing->roastery_id !== $roastery->id || $existing->currency !== $currency) {
                    throw new ApiDomainException(
                        'settlement.batch_idempotency_conflict',
                        'کلید تکرارپذیری با Batch دیگری استفاده شده است.',
                        409,
                    );
                }

                return $this->loadBatch($existing);
            }

            $allocations = SettlementAllocation::query()
                ->where('owner_type', 'roastery')
                ->where('owner_id', $roastery->id)
                ->where('currency', $currency)
                ->where('status', SettlementAllocationStatus::Eligible->value)
                ->whereNotExists(static function ($query): void {
                    $query->selectRaw('1')
                        ->from('settlement_batch_allocations')
                        ->whereColumn(
                            'settlement_batch_allocations.settlement_allocation_id',
                            'settlement_allocations.id',
                        );
                })
                ->orderBy('eligible_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($allocations->isEmpty()) {
                throw new ApiDomainException(
                    'settlement.no_eligible_allocations',
                    'Allocation قابل‌تسویه‌ای برای این روستری وجود ندارد.',
                    409,
                );
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
                $allocation->forceFill([
                    'status' => SettlementAllocationStatus::Scheduled,
                    'scheduled_at' => $scheduledAt,
                ])->save();
            }

            $this->audit->record(
                'settlement.batch_created',
                actor: $actor,
                auditable: $batch,
                metadata: [
                    'roastery_id' => $roastery->id,
                    'allocation_count' => $batch->allocation_count,
                    'net_total' => $batch->net_total,
                    'currency' => $currency,
                ],
                request: $request,
            );

            return $this->loadBatch($batch);
        }, 3);
    }

    /** @param array{action: string, payout_reference?: string|null, failure_code?: string|null, failure_message?: string|null} $input */
    public function resolve(User $actor, SettlementBatch $batch, array $input, Request $request): SettlementBatch
    {
        return DB::transaction(function () use ($actor, $batch, $input, $request): SettlementBatch {
            $locked = SettlementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $action = $input['action'];
            $now = now();

            if ($action === 'process') {
                if (! in_array($locked->status, [SettlementBatchStatus::Pending, SettlementBatchStatus::Failed], true)) {
                    throw new ApiDomainException('settlement.batch_not_processable', 'این Batch قابل پردازش نیست.', 409);
                }
                $locked->forceFill([
                    'status' => SettlementBatchStatus::Processing,
                    'processed_by_id' => $actor->id,
                    'processing_at' => $now,
                    'failure_code' => null,
                    'failure_message' => null,
                    'failed_at' => null,
                ])->save();
            } elseif ($action === 'paid') {
                if ($locked->status !== SettlementBatchStatus::Processing) {
                    throw new ApiDomainException('settlement.batch_not_processing', 'Batch ابتدا باید در حال پردازش باشد.', 409);
                }
                $reference = trim((string) ($input['payout_reference'] ?? ''));
                if ($reference === '') {
                    throw new ApiDomainException('settlement.payout_reference_required', 'مرجع پرداخت الزامی است.', 422);
                }
                $locked->forceFill([
                    'status' => SettlementBatchStatus::Paid,
                    'processed_by_id' => $actor->id,
                    'payout_reference' => $reference,
                    'paid_at' => $now,
                ])->save();
                SettlementAllocation::query()
                    ->whereIn('id', SettlementBatchAllocation::query()
                        ->where('settlement_batch_id', $locked->id)
                        ->select('settlement_allocation_id'))
                    ->where('status', SettlementAllocationStatus::Scheduled->value)
                    ->update([
                        'status' => SettlementAllocationStatus::Paid->value,
                        'paid_at' => $now,
                    ]);
            } else {
                if ($locked->status !== SettlementBatchStatus::Processing) {
                    throw new ApiDomainException('settlement.batch_not_processing', 'Batch ابتدا باید در حال پردازش باشد.', 409);
                }
                $locked->forceFill([
                    'status' => SettlementBatchStatus::Failed,
                    'processed_by_id' => $actor->id,
                    'failure_code' => trim((string) ($input['failure_code'] ?? '')),
                    'failure_message' => isset($input['failure_message'])
                        ? trim((string) $input['failure_message']) ?: null
                        : null,
                    'failed_at' => $now,
                ])->save();
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
                    'failure_code' => $locked->failure_code,
                ],
                request: $request,
            );

            return $this->loadBatch($locked->refresh());
        }, 3);
    }

    public function loadBatch(SettlementBatch $batch): SettlementBatch
    {
        return $batch->load(['roastery', 'creator', 'processor', 'allocations']);
    }
}
