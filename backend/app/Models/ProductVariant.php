<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $product_id
 * @property string|null $sku
 * @property int $weight_grams
 * @property int $price
 * @property int|null $compare_at_price
 * @property string|null $currency
 * @property bool $is_active
 * @property int $stock_on_hand
 * @property int $stock_reserved
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product|null $product
 * @property-read Collection<int, StockLedgerEntry> $stockLedgerEntries
 * @property-read Collection<int, CheckoutQuoteItem> $quoteItems
 * @property-read Collection<int, OrderItem> $orderItems
 * @property-read Collection<int, InventoryReservation> $reservations
 */
final class ProductVariant extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_id',
        'sku',
        'weight_grams',
        'price',
        'compare_at_price',
        'currency',
        'is_active',
        'stock_on_hand',
        'stock_reserved',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'integer',
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'is_active' => 'boolean',
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<StockLedgerEntry, $this> */
    public function stockLedgerEntries(): HasMany
    {
        return $this->hasMany(StockLedgerEntry::class, 'variant_id');
    }

    /** @return HasMany<CheckoutQuoteItem, $this> */
    public function quoteItems(): HasMany
    {
        return $this->hasMany(CheckoutQuoteItem::class, 'variant_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    /** @return HasMany<InventoryReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'variant_id');
    }

    public function availableQuantity(): int
    {
        return max(0, $this->stock_on_hand - $this->stock_reserved);
    }
}
