<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContentRelationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relation_type' => $this->relation_type,
            'target_type' => $this->target_type,
            'target_key' => $this->target_key,
            'anchor_text' => $this->anchor_text,
            'position' => $this->position,
        ];
    }
}
