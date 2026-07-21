<?php

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentReconciliation extends Model
{
    use HasUlids;

    protected $fillable = [
        'payment_attempt_id',
        'order_id',
        'status',
        'code',
        'message',
        'details',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReconciliationStatus::class,
            'details' => 'encrypted:array',
            'resolved_at' => 'immutable_datetime',
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
}
