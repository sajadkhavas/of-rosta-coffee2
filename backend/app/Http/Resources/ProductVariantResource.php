<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductVariantResource extends JsonResource
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
        ];
    }
}
