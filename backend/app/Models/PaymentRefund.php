<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentRefund extends Model
{
    use HasUlids;

    protected $fillable = [
        'payment_attempt_id',
        'order_id',
        'requested_by',
        'status',
        'amount',
        'currency',
        'reason',
        'provider_refund_id',
        'idempotency_key',
        'request_hash',
        'provider_metadata',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'provider_metadata' => 'encrypted:array',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
