<?php

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $payment_attempt_id
 * @property string|null $refund_attempt_id
 * @property string|null $opened_by
 * @property string|null $resolved_by
 * @property string|null $kind
 * @property ReconciliationStatus $status
 * @property string|null $severity
 * @property string|null $deduplication_key
 * @property string|null $summary
 * @property array<mixed> $details
 * @property string|null $resolution
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order|null $order
 * @property-read PaymentAttempt|null $paymentAttempt
 * @property-read RefundAttempt|null $refundAttempt
 * @property-read User|null $opener
 * @property-read User|null $resolver
 */
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

    /** @return BelongsTo<RefundAttempt, $this> */
    public function refundAttempt(): BelongsTo
    {
        return $this->belongsTo(RefundAttempt::class);
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
