<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $quote_id
 * @property string|null $roastery_id
 * @property int $subtotal
 * @property int $packaging_total
 * @property int $grinding_total
 * @property int $shipping_total
 * @property int $discount_total
 * @property int $tax_total
 * @property int $grand_total
 * @property string $currency
 * @property array<mixed>|null $pricing_snapshot
 * @property-read CheckoutQuote|null $quote
 * @property-read Roastery|null $roastery
 * @property-read Collection<int, CheckoutQuoteItem> $items
 * @property-read Collection<int, CheckoutQuoteItemService> $services
 */
final class CheckoutQuoteGroup extends Model
{
    use HasUlids;

    protected $fillable = [
        'quote_id',
        'roastery_id',
        'subtotal',
        'packaging_total',
        'grinding_total',
        'shipping_total',
        'discount_total',
        'tax_total',
        'grand_total',
        'currency',
        'pricing_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'packaging_total' => 'integer',
            'grinding_total' => 'integer',
            'shipping_total' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'pricing_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<CheckoutQuote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class, 'quote_id');
    }

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return HasMany<CheckoutQuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CheckoutQuoteItem::class, 'group_id');
    }

    /** @return HasMany<CheckoutQuoteItemService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(CheckoutQuoteItemService::class, 'quote_group_id');
    }
}
