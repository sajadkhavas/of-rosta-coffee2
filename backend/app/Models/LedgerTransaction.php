<?php

namespace App\Models;

use App\Enums\LedgerTransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class LedgerTransaction extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'payment_attempt_id',
        'type',
        'reference_key',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerTransactionType::class,
            'metadata' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Ledger transactions are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Ledger transactions are append-only.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id');
    }
}
