<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SeoRedirectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_path' => $this->source_path,
            'destination_path' => $this->destination_path,
            'status_code' => $this->status_code,
            'is_active' => $this->is_active,
            'hits' => $this->hits,
            'last_hit_at' => $this->last_hit_at?->toIso8601String(),
        ];
    }
}
