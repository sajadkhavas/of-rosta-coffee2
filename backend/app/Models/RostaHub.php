<?php

namespace App\Models;

use App\Enums\FeeMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $province
 * @property string $city
 * @property FeeMode $fee_mode
 * @property int $fee_amount
 * @property int $preparation_minutes
 * @property int|null $capacity_per_day
 * @property array<int> $supported_weights
 * @property bool $is_active
 * @property-read Collection<int, GrindingProfile> $profiles
 * @property-read Collection<int, RostaHubServiceZone> $serviceZones
 */
final class RostaHub extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'name',
        'province',
        'city',
        'fee_mode',
        'fee_amount',
        'preparation_minutes',
        'capacity_per_day',
        'supported_weights',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee_mode' => FeeMode::class,
            'fee_amount' => 'integer',
            'preparation_minutes' => 'integer',
            'capacity_per_day' => 'integer',
            'supported_weights' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<GrindingProfile, $this> */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(
            GrindingProfile::class,
            'rosta_hub_grinding_profile',
            'hub_id',
            'grinding_profile_id',
        )->withTimestamps();
    }

    /** @return HasMany<RostaHubServiceZone, $this> */
    public function serviceZones(): HasMany
    {
        return $this->hasMany(RostaHubServiceZone::class, 'hub_id');
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(HubWorkItem::class, 'hub_id');
    }
}
