<?php

namespace App\Models;

use App\Enums\OtpDeliveryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $mobile
 * @property string|null $purpose
 * @property string|null $code_digest
 * @property int $attempts
 * @property int $max_attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $resend_available_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $locked_at
 * @property string|null $requested_ip_hash
 * @property string|null $user_agent_hash
 * @property OtpDeliveryStatus $delivery_status
 * @property int $delivery_attempts
 * @property string|null $delivery_provider
 * @property string|null $provider_message_id
 * @property string|null $delivery_error_code
 * @property CarbonImmutable|null $delivery_started_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $delivery_failed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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
        'delivery_status',
        'delivery_attempts',
        'delivery_provider',
        'provider_message_id',
        'delivery_error_code',
        'delivery_started_at',
        'delivered_at',
        'delivery_failed_at',
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
            'delivery_status' => OtpDeliveryStatus::class,
            'delivery_attempts' => 'integer',
            'delivery_started_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'delivery_failed_at' => 'immutable_datetime',
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
