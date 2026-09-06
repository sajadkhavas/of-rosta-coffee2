<?php

namespace App\Http\Resources;

use App\Models\Cafe;
use Illuminate\Http\Request;

/** @mixin Cafe */
final class CafeResource extends OkJsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'is_verified' => $this->isPubliclyVisible(),
            'city' => $this->city,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'website_url' => $this->website_url,
            'instagram_handle' => $this->instagram_handle,
            'description' => $this->description,
            'opening_hours' => $this->opening_hours ?? [],
            'amenities' => $this->amenities ?? [],
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
