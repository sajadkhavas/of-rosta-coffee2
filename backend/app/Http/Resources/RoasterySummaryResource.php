<?php

namespace App\Http\Resources;

use App\Models\Roastery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Roastery */
class RoasterySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $preparation = null;
        if ($this->preparation_min_hours !== null && $this->preparation_max_hours !== null) {
            $preparation = [
                'min_hours' => $this->preparation_min_hours,
                'max_hours' => $this->preparation_max_hours,
            ];
        }

        $rating = null;
        if ($this->rating_value !== null) {
            $rating = [
                'value' => (float) $this->rating_value,
                'count' => (int) $this->rating_count,
            ];
        }

        $grindingCapability = null;
        if (
            $this->relationLoaded('grindingCapability')
            && $this->grindingCapability?->is_active
        ) {
            $grindingCapability = new GrindingCapabilityResource($this->grindingCapability);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'city' => $this->city,
            'is_verified' => $this->isPubliclyVisible(),
            'logo' => $this->logo ? new MediaAssetResource($this->logo) : null,
            'cover' => $this->cover ? new MediaAssetResource($this->cover) : null,
            'preparation_time' => $preparation,
            'rating' => $rating,
            'grinding_capability' => $grindingCapability,
        ];
    }
}
