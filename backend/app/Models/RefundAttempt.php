<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $payment_attempt_id
 * @property string|null $requested_by
 * @property string|null $approved_by
 * @property string|null $resolved_by
 * @property string|null $provider
 * @property RefundStatus $status
 * @property int $amount
 * @property string|null $currency
 * @property string|null $idempotency_key
 * @property string|null $request_hash
 * @property string|null $reason
 * @property string|null $provider_reference
 * @property string|null $provider_code
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property array<mixed> $request_payload
 * @property array<mixed> $response_payload
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $processing_at
 * @property CarbonImmutable|null $succeeded_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order|null $order
 * @property-read PaymentAttempt|null $paymentAttempt
 * @property-read User|null $requester
 * @property-read User|null $approver
 * @property-read User|null $resolver
 */
final class RefundAttempt extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'payment_attempt_id',
        'requested_by',
        'approved_by',
        'resolved_by',
        'provider',
        'status',
        'amount',
        'currency',
        'idempotency_key',
        'request_hash',
        'reason',
        'provider_reference',
        'provider_code',
        'failure_code',
        'failure_message',
        'request_payload',
        'response_payload',
        'approved_at',
        'processing_at',
        'succeeded_at',
        'failed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => 'integer',
            'request_payload' => 'encrypted:array',
            'response_payload' => 'encrypted:array',
            'approved_at' => 'immutable_datetime',
            'processing_at' => 'immutable_datetime',
            'succeeded_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
