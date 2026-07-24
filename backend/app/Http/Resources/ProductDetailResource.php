<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/** @mixin Product */
final class ProductDetailResource extends ProductSummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description ?? '',
            'gallery' => MediaAssetResource::collection($this->gallery),
            'brewing_suggestions' => array_values($this->brewing_suggestions ?? []),
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
            ],
        ];
    }
}
