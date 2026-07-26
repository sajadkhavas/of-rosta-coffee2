<?php

namespace App\Http\Resources;

use App\Models\GrindingProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GrindingProfile */
final class GrindingProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'version' => $this->version,
            'public_name' => $this->public_name,
            'brew_method' => $this->brew_method,
        ];
    }
}
