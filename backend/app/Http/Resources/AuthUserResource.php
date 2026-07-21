<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('roleAssignments');

        return [
            'id' => $this->resource->id,
            'mobile' => $this->resource->mobile,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'roles' => $this->resource->roleNames(),
        ];
    }
}
