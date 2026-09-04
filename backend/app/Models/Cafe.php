<?php

namespace App\Models;

use App\Enums\CafeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CafeMembership::class);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === CafeStatus::Verified && $this->verified_at !== null;
    }
}
