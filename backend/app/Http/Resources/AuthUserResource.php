<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/** @mixin User */
final class AuthUserResource extends OkJsonResource
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
