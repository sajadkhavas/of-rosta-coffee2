<?php

namespace App\Models;

use App\Enums\RoasteryMembershipRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $roastery_id
 * @property string $user_id
 * @property RoasteryMembershipRole $role
 * @property bool $is_locked
 * @property CarbonImmutable|null $locked_at
 * @property string|null $locked_by
 * @property string|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Roastery|null $roastery
 * @property-read User|null $user
 */
final class RoasteryMembership extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'user_id',
        'role',
        'is_locked',
        'locked_at',
        'locked_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => RoasteryMembershipRole::class,
            'is_locked' => 'boolean',
            'locked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
