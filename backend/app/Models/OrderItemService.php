<?php

namespace App\Models;

use App\Enums\OrderItemServiceStatus;
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
 * @property string $service_type
 * @property string $provider_type
 * @property string|null $provider_roastery_id
 * @property string|null $grinding_profile_id
 * @property OrderItemServiceStatus $status
 * @property int $service_fee
 * @property int $packaging_fee
 * @property int $shipping_fee
 * @property int $tax_amount
 * @property int $total_amount
 * @property string $currency
 * @property array<mixed> $pricing_snapshot
 * @property array<mixed> $service_snapshot
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $failed_at
 * @property-read Order|null $order
 * @property-read SubOrder|null $subOrder
 * @property-read OrderItem|null $orderItem
 * @property-read Roastery|null $providerRoastery
 * @property-read GrindingProfile|null $grindingProfile
 * @property-read Collection<int, ShipmentLeg> $shipmentLegs
 * @property-read Collection<int, SettlementAllocation> $settlementAllocations
 * @property-read Collection<int, OrderEvent> $events
 */
final class OrderItemService extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'order_item_id',
        'service_type',
        'provider_type',
        'provider_roastery_id',
        'grinding_profile_id',
        'status',
        'service_fee',
        'packaging_fee',
        'shipping_fee',
        'tax_amount',
        'total_amount',
        'currency',
        'pricing_snapshot',
        'service_snapshot',
        'failure_code',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderItemServiceStatus::class,
            'service_fee' => 'integer',
            'packaging_fee' => 'integer',
            'shipping_fee' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'pricing_snapshot' => 'array',
            'service_snapshot' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
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

    /** @return BelongsTo<Roastery, $this> */
    public function providerRoastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class, 'provider_roastery_id');
    }

    /** @return BelongsTo<GrindingProfile, $this> */
    public function grindingProfile(): BelongsTo
    {
        return $this->belongsTo(GrindingProfile::class);
    }

    /** @return HasMany<ShipmentLeg, $this> */
    public function shipmentLegs(): HasMany
    {
        return $this->hasMany(ShipmentLeg::class);
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
