<?php

namespace App\Models;

use App\Enums\SubOrderAcceptanceStatus;
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
 * @property SubOrderAcceptanceStatus $acceptance_status
 * @property int $subtotal
 * @property int $shipping_total
 * @property int $packaging_total
 * @property int $grinding_total
 * @property int $discount_total
 * @property int $tax_total
 * @property int $grand_total
 * @property int $commission_total
 * @property int $payable_total
 * @property string $currency
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $preparing_at
 * @property CarbonImmutable|null $ready_to_ship_at
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $customer_cancelled_at
 * @property string|null $rejection_reason
 * @property string|null $rejection_code
 * @property string|null $cancellation_code
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order|null $order
 * @property-read Roastery|null $roastery
 * @property-read Collection<int, OrderItem> $items
 * @property-read Shipment|null $shipment
 * @property-read Collection<int, SubOrderStatusHistory> $statusHistory
 * @property-read Collection<int, OrderInternalNote> $internalNotes
 * @property-read Collection<int, OrderItemService> $itemServices
 * @property-read Collection<int, ShipmentLeg> $shipmentLegs
 * @property-read Collection<int, SettlementAllocation> $settlementAllocations
 * @property-read Collection<int, OrderTaxLine> $taxLines
 * @property-read Collection<int, OrderEvent> $events
 */
final class SubOrder extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'roastery_id',
        'status',
        'acceptance_status',
        'subtotal',
        'shipping_total',
        'packaging_total',
        'grinding_total',
        'discount_total',
        'tax_total',
        'grand_total',
        'commission_total',
        'payable_total',
        'currency',
        'accepted_at',
        'rejected_at',
        'preparing_at',
        'ready_to_ship_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'customer_cancelled_at',
        'rejection_reason',
        'rejection_code',
        'cancellation_code',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubOrderStatus::class,
            'acceptance_status' => SubOrderAcceptanceStatus::class,
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'packaging_total' => 'integer',
            'grinding_total' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'commission_total' => 'integer',
            'payable_total' => 'integer',
            'accepted_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'preparing_at' => 'immutable_datetime',
            'ready_to_ship_at' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'customer_cancelled_at' => 'immutable_datetime',
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

    /** @return HasMany<OrderItemService, $this> */
    public function itemServices(): HasMany
    {
        return $this->hasMany(OrderItemService::class);
    }

    /** @return HasMany<ShipmentLeg, $this> */
    public function shipmentLegs(): HasMany
    {
        return $this->hasMany(ShipmentLeg::class)->orderBy('sequence');
    }

    /** @return HasMany<SettlementAllocation, $this> */
    public function settlementAllocations(): HasMany
    {
        return $this->hasMany(SettlementAllocation::class);
    }

    /** @return HasMany<OrderTaxLine, $this> */
    public function taxLines(): HasMany
    {
        return $this->hasMany(OrderTaxLine::class);
    }

    /** @return HasMany<OrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('occurred_at');
    }

    public function isCustomerCancellable(): bool
    {
        return $this->acceptance_status->customerCancellable();
    }
}
