<?php

namespace App\Models;

use App\Enums\SettlementBatchStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $roastery_id
 * @property string|null $created_by_id
 * @property string|null $processed_by_id
 * @property string|null $confirmed_by_id
 * @property SettlementBatchStatus $status
 * @property string $currency
 * @property int $gross_total
 * @property int $discount_total
 * @property int $tax_total
 * @property int $net_total
 * @property int $allocation_count
 * @property string $idempotency_key
 * @property string|null $payout_reference
 * @property string|null $payout_method
 * @property string|null $payout_evidence_hash
 * @property array<mixed>|null $payout_evidence
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $processing_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $failed_at
 * @property-read Roastery|null $roastery
 * @property-read User|null $creator
 * @property-read User|null $processor
 * @property-read User|null $confirmer
 * @property-read Collection<int, SettlementAllocation> $allocations
 * @property-read Collection<int, SettlementBatchAllocation> $memberships
 */
final class SettlementBatch extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'created_by_id',
        'processed_by_id',
        'confirmed_by_id',
        'status',
        'currency',
        'gross_total',
        'discount_total',
        'tax_total',
        'net_total',
        'allocation_count',
        'idempotency_key',
        'payout_reference',
        'payout_method',
        'payout_evidence_hash',
        'payout_evidence',
        'failure_code',
        'failure_message',
        'scheduled_at',
        'processing_at',
        'paid_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SettlementBatchStatus::class,
            'gross_total' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'net_total' => 'integer',
            'allocation_count' => 'integer',
            'payout_evidence' => 'encrypted:array',
            'scheduled_at' => 'immutable_datetime',
            'processing_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    public function allocations(): BelongsToMany
    {
        return $this->belongsToMany(
            SettlementAllocation::class,
            'settlement_batch_allocations',
        )->withPivot([
            'gross_amount',
            'discount_amount',
            'tax_amount',
            'net_amount',
            'attached_at',
        ]);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SettlementBatchAllocation::class);
    }
}
