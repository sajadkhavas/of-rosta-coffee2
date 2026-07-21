<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class OtpChallenge extends Model
{
    use HasUlids;

    protected $fillable = [
        'mobile',
        'purpose',
        'code_digest',
        'attempts',
        'max_attempts',
        'expires_at',
        'resend_available_at',
        'consumed_at',
        'locked_at',
        'requested_ip_hash',
        'user_agent_hash',
    ];

    protected $hidden = [
        'code_digest',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'resend_available_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->attempts >= $this->max_attempts;
    }
}
