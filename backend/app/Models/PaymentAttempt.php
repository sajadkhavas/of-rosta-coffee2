<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentAttempt extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'user_id',
        'provider',
        'attempt_number',
        'idempotency_key',
        'request_hash',
        'status',
        'amount',
        'amount_provider',
        'currency',
        'authority',
        'reference_id',
        'redirect_url',
        'gateway_code',
        'failure_code',
        'failure_message',
        'request_payload',
        'response_payload',
        'verification_payload',
        'expires_at',
        'verified_at',
        'failed_at',
        'cancelled_at',
        'review_required_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'attempt_number' => 'integer',
            'amount' => 'integer',
            'amount_provider' => 'integer',
            'request_payload' => 'encrypted:array',
            'response_payload' => 'encrypted:array',
            'verification_payload' => 'encrypted:array',
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'review_required_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refundAttempts(): HasMany
    {
        return $this->hasMany(RefundAttempt::class);
    }

    public function reconciliationCases(): HasMany
    {
        return $this->hasMany(FinancialReconciliationCase::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function isVerified(): bool
    {
        return $this->status === PaymentAttemptStatus::Verified;
    }
}
