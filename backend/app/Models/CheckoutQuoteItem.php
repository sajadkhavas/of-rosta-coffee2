<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $quote_id
 * @property string|null $product_id
 * @property string|null $variant_id
 * @property string|null $roast_batch_id
 * @property int $quantity
 * @property int $unit_price
 * @property int|null $compare_at_price
 * @property int $line_total
 * @property array<mixed> $product_snapshot
 * @property array<mixed> $variant_snapshot
 * @property array<mixed> $roast_batch_snapshot
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CheckoutQuote|null $quote
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 * @property-read RoastBatch|null $roastBatch
 */
final class CheckoutQuoteItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'quote_id',
        'product_id',
        'variant_id',
        'roast_batch_id',
        'quantity',
        'unit_price',
        'compare_at_price',
        'line_total',
        'product_snapshot',
        'variant_snapshot',
        'roast_batch_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'compare_at_price' => 'integer',
            'line_total' => 'integer',
            'product_snapshot' => 'array',
            'variant_snapshot' => 'array',
            'roast_batch_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<CheckoutQuote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class, 'quote_id');
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
}
