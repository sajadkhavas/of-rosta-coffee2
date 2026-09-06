<?php

namespace App\Models;

use App\Enums\CafeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $owner_user_id
 * @property string $name
 * @property string $slug
 * @property CafeStatus $status
 * @property string $city
 * @property string $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $phone
 * @property string|null $website_url
 * @property string|null $instagram_handle
 * @property string|null $description
 * @property array<mixed>|null $opening_hours
 * @property array<mixed>|null $amenities
 * @property CarbonImmutable|null $verified_at
 * @property string|null $reviewed_by_id
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property-read User|null $owner
 * @property-read User|null $reviewer
 * @property-read Collection<int, CafeMembership> $memberships
 */
final class Cafe extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'status',
        'city',
        'address',
        'latitude',
        'longitude',
        'phone',
        'website_url',
        'instagram_handle',
        'description',
        'opening_hours',
        'amenities',
        'verified_at',
        'reviewed_by_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => CafeStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'opening_hours' => 'array',
            'amenities' => 'array',
            'verified_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'review_note' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /** @return HasMany<CafeMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(CafeMembership::class);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === CafeStatus::Verified && $this->verified_at !== null;
    }
}
