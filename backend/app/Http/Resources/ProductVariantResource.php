<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use App\Models\WholesalePriceTier;
use Illuminate\Http\Request;

/** @mixin ProductVariant */
final class ProductVariantResource extends OkJsonResource
{
    public function toArray(Request $request): array
    {
        $available = $this->is_active && $this->availableQuantity() > 0;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'weight_grams' => $this->weight_grams,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'currency' => $this->currency,
            'is_available' => $available,
            'available_quantity' => $this->availableQuantity(),
            'wholesale_tiers' => $this->whenLoaded('wholesaleTiers', fn (): array => $this->wholesaleTiers
                ->where('is_active', true)
                ->sortBy('min_weight_grams')
                ->values()
                ->map(static fn (WholesalePriceTier $tier): array => [
                    'min_weight_grams' => $tier->min_weight_grams,
                    'unit_price' => $tier->unit_price,
                ])
                ->all()),
        ];
    }
}
