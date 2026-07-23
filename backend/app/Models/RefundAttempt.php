<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
