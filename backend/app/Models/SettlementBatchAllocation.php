<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $settlement_batch_id
 * @property string $settlement_allocation_id
 * @property int $gross_amount
 * @property int $discount_amount
 * @property int $tax_amount
 * @property int $net_amount
 * @property CarbonImmutable $attached_at
 */
final class SettlementBatchAllocation extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'settlement_batch_id',
        'settlement_allocation_id',
        'gross_amount',
        'discount_amount',
        'tax_amount',
        'net_amount',
        'attached_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'net_amount' => 'integer',
            'attached_at' => 'immutable_datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SettlementBatch::class, 'settlement_batch_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(SettlementAllocation::class, 'settlement_allocation_id');
    }
}
