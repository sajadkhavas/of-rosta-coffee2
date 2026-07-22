<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentEntrySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'canonical_path' => $this->canonical_path,
            'excerpt' => $this->excerpt,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'author' => $this->author
                ? new ContentAuthorResource($this->author)
                : null,
            'seo' => [
                'title' => $this->seo_title ?: $this->title,
                'description' => $this->seo_description ?: $this->excerpt,
                'canonical_path' => $this->canonical_path,
                'robots_index' => $this->robots_index,
                'robots_follow' => $this->robots_follow,
                'og_title' => $this->og_title ?: ($this->seo_title ?: $this->title),
                'og_description' => $this->og_description ?: ($this->seo_description ?: $this->excerpt),
                'og_media_url' => $this->og_media_url,
                'schema_type' => $this->schema_type,
            ],
            'keywords' => array_values($this->keywords ?? []),
        ];
    }
}
