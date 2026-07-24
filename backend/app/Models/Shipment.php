<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $sub_order_id
 * @property string|null $carrier
 * @property string|null $tracking_code
 * @property string|null $status
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SubOrder|null $subOrder
 */
final class Shipment extends Model
{
    use HasUlids;

    protected $fillable = [
        'sub_order_id',
        'carrier',
        'tracking_code',
        'status',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<SubOrder, $this> */
    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
