<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $bio
 * @property array<mixed> $credentials
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ContentEntry> $entries
 */
final class ContentAuthor extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'bio',
        'credentials',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ContentEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ContentEntry::class, 'author_id');
    }
}
