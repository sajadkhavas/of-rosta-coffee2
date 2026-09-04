<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
