<?php

namespace App\Models;

use App\Enums\FeeMode;
use App\Enums\GrindingAvailability;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string|null $roastery_id
 * @property GrindingAvailability $availability
 * @property FeeMode $fee_mode
 * @property int $fee_amount
 * @property int $preparation_minutes
 * @property int|null $capacity_per_day
 * @property array<int> $supported_weights
 * @property bool $is_active
 * @property-read Roastery|null $roastery
 * @property-read Collection<int, GrindingProfile> $profiles
 */
final class RoasteryGrindingCapability extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'availability',
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
            'availability' => GrindingAvailability::class,
            'fee_mode' => FeeMode::class,
            'fee_amount' => 'integer',
            'preparation_minutes' => 'integer',
            'capacity_per_day' => 'integer',
            'supported_weights' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return BelongsToMany<GrindingProfile, $this> */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(
            GrindingProfile::class,
            'roastery_grinding_profile',
            'capability_id',
            'grinding_profile_id',
        )->withTimestamps();
    }
}
