<?php

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinancialReconciliationCase extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'payment_attempt_id',
        'refund_attempt_id',
        'opened_by',
        'resolved_by',
        'kind',
        'status',
        'severity',
        'deduplication_key',
        'summary',
        'details',
        'resolution',
        'opened_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReconciliationStatus::class,
            'details' => 'encrypted:array',
            'resolution' => 'encrypted',
            'opened_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
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

    public function refundAttempt(): BelongsTo
    {
        return $this->belongsTo(RefundAttempt::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
