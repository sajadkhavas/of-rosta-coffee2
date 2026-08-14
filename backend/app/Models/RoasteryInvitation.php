<?php

namespace App\Models;

use App\Enums\RoasteryMembershipRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }
}
