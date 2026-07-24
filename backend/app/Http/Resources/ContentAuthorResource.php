<?php

namespace App\Http\Resources;

use App\Models\ContentAuthor;
use Illuminate\Http\Request;

/** @mixin ContentAuthor */
final class ContentAuthorResource extends OkJsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'bio' => $this->bio,
            'credentials' => array_values($this->credentials ?? []),
            'is_active' => $this->is_active,
        ];
    }
}
