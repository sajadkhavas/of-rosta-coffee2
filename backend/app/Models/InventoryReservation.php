<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $order_item_id
 * @property string|null $variant_id
 * @property string|null $roast_batch_id
 * @property int $quantity
 * @property ReservationStatus $status
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order|null $order
 * @property-read OrderItem|null $orderItem
 * @property-read ProductVariant|null $variant
 * @property-read RoastBatch|null $roastBatch
 */
final class InventoryReservation extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'variant_id',
        'roast_batch_id',
        'quantity',
        'status',
        'expires_at',
        'released_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => ReservationStatus::class,
            'expires_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** @return BelongsTo<RoastBatch, $this> */
    public function roastBatch(): BelongsTo
    {
        return $this->belongsTo(RoastBatch::class);
    }
}
