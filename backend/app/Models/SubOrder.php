<?php

namespace App\Models;

use App\Enums\SubOrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $roastery_id
 * @property SubOrderStatus $status
 * @property int $subtotal
 * @property int $shipping_total
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $preparing_at
 * @property CarbonImmutable|null $ready_to_ship_at
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order|null $order
 * @property-read Roastery|null $roastery
 * @property-read Collection<int, OrderItem> $items
 * @property-read Shipment|null $shipment
 * @property-read Collection<int, SubOrderStatusHistory> $statusHistory
 * @property-read Collection<int, OrderInternalNote> $internalNotes
 */
final class SubOrder extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'roastery_id',
        'status',
        'subtotal',
        'shipping_total',
        'accepted_at',
        'rejected_at',
        'preparing_at',
        'ready_to_ship_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubOrderStatus::class,
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'accepted_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'preparing_at' => 'immutable_datetime',
            'ready_to_ship_at' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasOne<Shipment, $this> */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    /** @return HasMany<SubOrderStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(SubOrderStatusHistory::class)
            ->orderBy('created_at');
    }

    /** @return HasMany<OrderInternalNote, $this> */
    public function internalNotes(): HasMany
    {
        return $this->hasMany(OrderInternalNote::class);
    }
}
