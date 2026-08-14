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
 * @property string $invited_by
 * @property string|null $target_user_id
 * @property string $target_mobile
 * @property string $target_mobile_hash
 * @property string $token_hash
 * @property RoasteryMembershipRole $role
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Roastery|null $roastery
 */
final class RoasteryInvitation extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'invited_by',
        'target_user_id',
        'target_mobile',
        'target_mobile_hash',
        'token_hash',
        'role',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'target_mobile' => 'encrypted',
            'role' => RoasteryMembershipRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }
}
