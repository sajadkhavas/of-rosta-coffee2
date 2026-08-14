<?php

namespace App\Models;

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
 * @property string|null $sub_order_id
 * @property string|null $product_id
 * @property string|null $variant_id
 * @property string|null $roast_batch_id
 * @property int $quantity
 * @property int $unit_price
 * @property int $line_total
 * @property int $discount_amount
 * @property int $tax_amount
 * @property int $commission_amount
 * @property int $net_amount
 * @property array<mixed> $financial_snapshot
 * @property array<mixed> $product_snapshot
 * @property array<mixed> $variant_snapshot
 * @property array<mixed> $roast_batch_snapshot
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order|null $order
 * @property-read SubOrder|null $subOrder
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 * @property-read RoastBatch|null $roastBatch
 * @property-read InventoryReservation|null $reservation
 * @property-read Collection<int, OrderItemService> $services
 * @property-read Collection<int, SettlementAllocation> $settlementAllocations
 * @property-read Collection<int, OrderEvent> $events
 */
final class OrderItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'product_id',
        'variant_id',
        'roast_batch_id',
        'quantity',
        'unit_price',
        'line_total',
        'discount_amount',
        'tax_amount',
        'commission_amount',
        'net_amount',
        'financial_snapshot',
        'product_snapshot',
        'variant_snapshot',
        'roast_batch_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'commission_amount' => 'integer',
            'net_amount' => 'integer',
            'financial_snapshot' => 'array',
            'product_snapshot' => 'array',
            'variant_snapshot' => 'array',
            'roast_batch_snapshot' => 'array',
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    /** @return HasOne<InventoryReservation, $this> */
    public function reservation(): HasOne
    {
        return $this->hasOne(InventoryReservation::class);
    }

    /** @return HasMany<OrderItemService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(OrderItemService::class);
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
