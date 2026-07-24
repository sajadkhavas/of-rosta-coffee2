<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $user_id
 * @property string|null $provider
 * @property int $attempt_number
 * @property string|null $idempotency_key
 * @property string|null $request_hash
 * @property PaymentAttemptStatus $status
 * @property int $amount
 * @property int $amount_provider
 * @property string|null $currency
 * @property string|null $authority
 * @property string|null $reference_id
 * @property string|null $redirect_url
 * @property string|null $gateway_code
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property array<mixed> $request_payload
 * @property array<mixed> $response_payload
 * @property array<mixed> $verification_payload
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $review_required_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order|null $order
 * @property-read User|null $user
 * @property-read Collection<int, RefundAttempt> $refundAttempts
 * @property-read Collection<int, FinancialReconciliationCase> $reconciliationCases
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> ownedBy()
 */
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RefundAttempt, $this> */
    public function refundAttempts(): HasMany
    {
        return $this->hasMany(RefundAttempt::class);
    }

    /** @return HasMany<FinancialReconciliationCase, $this> */
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
