<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentAttempt extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'order_id',
        'provider',
        'status',
        'amount',
        'currency',
        'idempotency_key',
        'request_hash',
        'redirect_url',
        'provider_authority',
        'provider_transaction_id',
        'failure_code',
        'failure_message',
        'provider_metadata',
        'initiated_at',
        'verified_at',
        'failed_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'provider_metadata' => 'encrypted:array',
            'initiated_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }
}
