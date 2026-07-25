<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $sub_order_id
 * @property string|null $order_item_id
 * @property string|null $order_item_service_id
 * @property string|null $shipment_leg_id
 * @property string $event_type
 * @property string|null $previous_state
 * @property string|null $next_state
 * @property string $actor_type
 * @property string|null $actor_user_id
 * @property string|null $request_id
 * @property string|null $reason_code
 * @property string $customer_title
 * @property string|null $customer_description
 * @property array<mixed>|null $internal_metadata
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $created_at
 */
final class OrderEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'order_item_id',
        'order_item_service_id',
        'shipment_leg_id',
        'event_type',
        'previous_state',
        'next_state',
        'actor_type',
        'actor_user_id',
        'actor_reference',
        'request_id',
        'reason_code',
        'customer_title',
        'customer_description',
        'internal_metadata',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'internal_metadata' => 'encrypted:array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Order events are append-only and cannot be updated.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Order events are append-only and cannot be deleted.');
        });
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<SubOrder, $this> */
    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<OrderItemService, $this> */
    public function orderItemService(): BelongsTo
    {
        return $this->belongsTo(OrderItemService::class);
    }

    /** @return BelongsTo<ShipmentLeg, $this> */
    public function shipmentLeg(): BelongsTo
    {
        return $this->belongsTo(ShipmentLeg::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
