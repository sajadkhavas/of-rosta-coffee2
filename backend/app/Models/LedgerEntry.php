<?php

namespace App\Models;

use App\Enums\LedgerAccount;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class LedgerEntry extends Model
{
    use HasUlids;

    protected $fillable = [
        'transaction_id',
        'roastery_id',
        'account',
        'debit',
        'credit',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'account' => LedgerAccount::class,
            'debit' => 'integer',
            'credit' => 'integer',
            'metadata' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (LedgerEntry $entry): void {
            if (($entry->debit > 0) === ($entry->credit > 0)) {
                throw new LogicException('Exactly one of debit or credit must be positive.');
            }
        });

        static::updating(static function (): never {
            throw new LogicException('Ledger entries are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Ledger entries are append-only.');
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }
}
