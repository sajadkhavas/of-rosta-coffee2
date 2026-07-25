<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property int $version
 * @property string $public_name
 * @property string $brew_method
 * @property array<mixed> $recipe_snapshot
 * @property bool $is_active
 * @property-read Collection<int, RoasteryGrindingCapability> $roasteryCapabilities
 * @property-read Collection<int, OrderItemService> $orderItemServices
 */
final class GrindingProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'version',
        'public_name',
        'brew_method',
        'recipe_snapshot',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'recipe_snapshot' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<RoasteryGrindingCapability, $this> */
    public function roasteryCapabilities(): BelongsToMany
    {
        return $this->belongsToMany(
            RoasteryGrindingCapability::class,
            'roastery_grinding_profile',
            'grinding_profile_id',
            'capability_id',
        )->withTimestamps();
    }

    /** @return HasMany<OrderItemService, $this> */
    public function orderItemServices(): HasMany
    {
        return $this->hasMany(OrderItemService::class);
    }
}
