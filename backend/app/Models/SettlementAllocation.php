<?php

namespace App\Models;

use App\Enums\SettlementAllocationStatus;
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
 * @property string|null $order_item_id
 * @property string|null $order_item_service_id
 * @property string|null $shipment_leg_id
 * @property string $allocation_type
 * @property string $owner_type
 * @property string|null $owner_id
 * @property SettlementAllocationStatus $status
 * @property int $gross_amount
 * @property int $discount_amount
 * @property int $tax_amount
 * @property int $net_amount
 * @property string $currency
 * @property array<mixed>|null $metadata
 * @property CarbonImmutable|null $eligible_at
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $reversed_at
 * @property-read Order|null $order
 * @property-read SubOrder|null $subOrder
 * @property-read OrderItem|null $orderItem
 * @property-read OrderItemService|null $orderItemService
 * @property-read ShipmentLeg|null $shipmentLeg
 * @property-read Collection<int, OrderTaxLine> $taxLines
 * @property-read Collection<int, SettlementBatchAllocation> $batchMemberships
 */
final class SettlementAllocation extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'order_item_id',
        'order_item_service_id',
        'shipment_leg_id',
        'allocation_type',
        'owner_type',
        'owner_id',
        'status',
        'gross_amount',
        'discount_amount',
        'tax_amount',
        'net_amount',
        'currency',
        'tax_code',
        'pricing_version',
        'source_reference',
        'idempotency_key',
        'metadata',
        'eligible_at',
        'scheduled_at',
        'paid_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SettlementAllocationStatus::class,
            'gross_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'net_amount' => 'integer',
            'metadata' => 'array',
            'eligible_at' => 'immutable_datetime',
            'scheduled_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
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

    /** @return HasMany<OrderTaxLine, $this> */
    public function taxLines(): HasMany
    {
        return $this->hasMany(OrderTaxLine::class);
    }

    /** @return HasMany<SettlementBatchAllocation, $this> */
    public function batchMemberships(): HasMany
    {
        return $this->hasMany(SettlementBatchAllocation::class);
    }
}
