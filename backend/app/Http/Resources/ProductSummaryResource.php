<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'origin' => new OriginResource($this->origin),
            'processing_method' => $this->processing_method->value,
            'roast_level' => $this->roast_level->value,
            'arabica_percentage' => $this->arabica_percentage,
            'tasting_notes' => array_values($this->tasting_notes ?? []),
            'primary_image' => $this->primaryImage
                ? new MediaAssetResource($this->primaryImage)
                : null,
            'roastery' => new RoasterySummaryResource($this->roastery),
            'variants' => ProductVariantResource::collection($this->variants),
            'latest_roast_batch' => $this->latestRoastBatch
                ? new RoastBatchResource($this->latestRoastBatch)
                : null,
            'status' => $this->status->value,
        ];
    }
}
