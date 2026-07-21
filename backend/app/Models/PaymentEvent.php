<?php

namespace App\Models;

use App\Enums\PaymentEventType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PaymentEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'payment_attempt_id',
        'type',
        'provider_event_id',
        'payload_hash',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentEventType::class,
            'payload' => 'encrypted:array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Payment events are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Payment events are append-only.');
        });
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }
}
