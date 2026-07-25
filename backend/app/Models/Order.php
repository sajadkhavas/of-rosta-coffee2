<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $roastery_id
 * @property string|null $quote_id
 * @property string|null $order_number
 * @property OrderStatus $status
 * @property array<mixed> $address_snapshot
 * @property string|null $notes
 * @property int $subtotal
 * @property int $shipping_total
 * @property int $discount_total
 * @property int $grand_total
 * @property string|null $currency
 * @property CarbonImmutable|null $placed_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Roastery|null $roastery
 * @property-read CheckoutQuote|null $quote
 * @property-read SubOrder|null $subOrder
 * @property-read Collection<int, SubOrder> $subOrders
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, InventoryReservation> $reservations
 * @property-read Collection<int, PaymentAttempt> $paymentAttempts
 * @property-read Collection<int, RefundAttempt> $refundAttempts
 * @property-read Collection<int, FinancialReconciliationCase> $reconciliationCases
 * @property-read Collection<int, OrderInternalNote> $internalNotes
 * @property-read Collection<int, OrderIdempotencyKey> $idempotencyKeys
 * @property-read Collection<int, OrderItemService> $itemServices
 * @property-read Collection<int, ShipmentLeg> $shipmentLegs
 * @property-read Collection<int, SettlementAllocation> $settlementAllocations
 * @property-read Collection<int, OrderTaxLine> $taxLines
 * @property-read Collection<int, OrderEvent> $events
 */
final class Order extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'roastery_id',
        'quote_id',
        'order_number',
        'status',
        'address_snapshot',
        'notes',
        'subtotal',
        'shipping_total',
        'discount_total',
        'grand_total',
        'currency',
        'placed_at',
        'paid_at',
        'refunded_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'address_snapshot' => 'encrypted:array',
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'discount_total' => 'integer',
            'grand_total' => 'integer',
            'placed_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Legacy single-roastery compatibility relation.
     *
     * R5 code must use subOrders() for authoritative marketplace behaviour.
     *
     * @return BelongsTo<Roastery, $this>
     */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return BelongsTo<CheckoutQuote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class, 'quote_id');
    }

    /**
     * Legacy single-sub-order compatibility relation.
     *
     * @return HasOne<SubOrder, $this>
     */
    public function subOrder(): HasOne
    {
        return $this->hasOne(SubOrder::class)->oldestOfMany();
    }

    /** @return HasMany<SubOrder, $this> */
    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class)->orderBy('created_at');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<InventoryReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /** @return HasMany<RefundAttempt, $this> */
    public function refundAttempts(): HasMany
    {
        return $this->hasMany(RefundAttempt::class);
    }

    /** @return HasMany<FinancialReconciliationCase, $this> */
    public function reconciliationCases(): HasMany
    {
        return $this->hasMany(FinancialReconciliationCase::class);
    }

    /** @return HasMany<OrderInternalNote, $this> */
    public function internalNotes(): HasMany
    {
        return $this->hasMany(OrderInternalNote::class);
    }

    /** @return HasMany<OrderIdempotencyKey, $this> */
    public function idempotencyKeys(): HasMany
    {
        return $this->hasMany(OrderIdempotencyKey::class);
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
}
