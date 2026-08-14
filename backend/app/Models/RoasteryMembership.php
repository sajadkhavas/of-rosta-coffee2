<?php

namespace App\Models;

use App\Enums\RoasteryMembershipRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
