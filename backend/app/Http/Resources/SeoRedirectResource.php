<?php

namespace App\Http\Resources;

use App\Models\SeoRedirect;
use Illuminate\Http\Request;

/** @mixin SeoRedirect */
final class SeoRedirectResource extends OkJsonResource
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
