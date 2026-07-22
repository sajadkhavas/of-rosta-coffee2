<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ContentEntry extends Model
{
    use HasUlids;

    protected $fillable = [
        'author_id',
        'reviewed_by',
        'type',
        'title',
        'slug',
        'canonical_path',
        'excerpt',
        'body',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'og_media_url',
        'schema_type',
        'keywords',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'status' => ContentStatus::class,
            'body' => 'array',
            'keywords' => 'array',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(ContentAuthor::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ContentRelation::class)->orderBy('position');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeIndexable(Builder $query): Builder
    {
        return $query->published()->where('robots_index', true);
    }
}
