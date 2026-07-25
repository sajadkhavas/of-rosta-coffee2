<?php

namespace App\Models;

use App\Enums\ShipmentLegStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $sub_order_id
 * @property string|null $order_item_service_id
 * @property string|null $legacy_shipment_id
 * @property string $route_type
 * @property int $sequence
 * @property ShipmentLegStatus $status
 * @property string|null $carrier
 * @property string|null $tracking_code
 * @property int $gross_amount
 * @property int $tax_amount
 * @property int $total_amount
 * @property string $currency
 * @property array<mixed>|null $origin_snapshot
 * @property array<mixed>|null $destination_snapshot
 * @property CarbonImmutable|null $planned_at
 * @property CarbonImmutable|null $picked_up_at
 * @property CarbonImmutable|null $delivered_at
 * @property-read Order|null $order
 * @property-read SubOrder|null $subOrder
 * @property-read OrderItemService|null $orderItemService
 * @property-read Shipment|null $legacyShipment
 * @property-read Collection<int, SettlementAllocation> $settlementAllocations
 * @property-read Collection<int, OrderEvent> $events
 */
final class ShipmentLeg extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'order_item_service_id',
        'legacy_shipment_id',
        'route_type',
        'sequence',
        'status',
        'carrier',
        'tracking_code',
        'charge_owner_type',
        'charge_owner_id',
        'gross_amount',
        'tax_amount',
        'total_amount',
        'currency',
        'origin_snapshot',
        'destination_snapshot',
        'planned_at',
        'picked_up_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'status' => ShipmentLegStatus::class,
            'gross_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'origin_snapshot' => 'encrypted:array',
            'destination_snapshot' => 'encrypted:array',
            'planned_at' => 'immutable_datetime',
            'picked_up_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
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

    /** @return BelongsTo<OrderItemService, $this> */
    public function orderItemService(): BelongsTo
    {
        return $this->belongsTo(OrderItemService::class);
    }

    /** @return BelongsTo<Shipment, $this> */
    public function legacyShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'legacy_shipment_id');
    }

    /** @return HasMany<SettlementAllocation, $this> */
    public function settlementAllocations(): HasMany
    {
        return $this->hasMany(SettlementAllocation::class);
    }

    /** @return HasMany<OrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }
}
