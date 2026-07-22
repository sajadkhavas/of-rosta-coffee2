<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class ContentEntryDetailResource extends ContentEntrySummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'body' => array_values($this->body ?? []),
            'content_hash' => $this->content_hash,
            'relations' => ContentRelationResource::collection($this->relations),
            'reviewed_by' => $this->reviewer?->id,
        ];
    }
}
