<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $author_id
 * @property string|null $reviewed_by
 * @property ContentType $type
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $canonical_path
 * @property string|null $excerpt
 * @property array<mixed> $body
 * @property ContentStatus $status
 * @property CarbonImmutable|null $published_at
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property bool $robots_index
 * @property bool $robots_follow
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_media_url
 * @property string|null $schema_type
 * @property array<mixed> $keywords
 * @property string|null $content_hash
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ContentAuthor|null $author
 * @property-read User|null $reviewer
 * @property-read Collection<int, ContentRelation> $relations
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> published()
 * @method static \Illuminate\Database\Eloquent\Builder<static> indexable()
 */
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

    /** @return BelongsTo<ContentAuthor, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(ContentAuthor::class, 'author_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<ContentRelation, $this> */
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
        return $this->scopePublished($query)->where('robots_index', true);
    }
}
