<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $product_variant_id
 * @property int $min_weight_grams
 * @property int $unit_price
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductVariant|null $variant
 */
final class WholesalePriceTier extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_variant_id',
        'min_weight_grams',
        'unit_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_weight_grams' => 'integer',
            'unit_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
