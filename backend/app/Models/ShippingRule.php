<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $roastery_id
 * @property string|null $province
 * @property string|null $city
 * @property int $base_cost
 * @property int|null $free_over
 * @property int $priority
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Roastery|null $roastery
 */
final class ShippingRule extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'province',
        'city',
        'base_cost',
        'free_over',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_cost' => 'integer',
            'free_over' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }
}
