<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuthSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'ip_hash',
        'user_agent_hash',
        'last_seen_at',
        'expires_at',
        'revoked_at',
        'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
