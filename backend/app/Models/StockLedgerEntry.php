<?php

namespace App\Models;

use App\Enums\StockReason;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class StockLedgerEntry extends Model
{
    use HasUlids;

    protected $fillable = [
        'variant_id',
        'roast_batch_id',
        'actor_id',
        'delta',
        'balance_after',
        'reason',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'balance_after' => 'integer',
            'reason' => StockReason::class,
            'metadata' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Stock ledger entries are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Stock ledger entries are append-only.');
        });
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function roastBatch(): BelongsTo
    {
        return $this->belongsTo(RoastBatch::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
