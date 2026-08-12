<?php

namespace App\Http\Resources;

use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MediaAsset */
final class MediaAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'blur_data_url' => $this->blur_data_url,
            'variant_version' => $this->variant_version,
            'sources' => array_values($this->sources ?? []),
        ];
    }
}
