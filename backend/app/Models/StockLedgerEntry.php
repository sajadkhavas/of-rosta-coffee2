<?php

namespace App\Models;

use App\Enums\StockReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string|null $variant_id
 * @property string|null $roast_batch_id
 * @property string|null $actor_id
 * @property int $delta
 * @property int $balance_after
 * @property StockReason $reason
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $idempotency_key
 * @property array<mixed> $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductVariant|null $variant
 * @property-read RoastBatch|null $roastBatch
 * @property-read User|null $actor
 */
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
        self::updating(static function (): never {
            throw new LogicException('Stock ledger entries are append-only.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Stock ledger entries are append-only.');
        });
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** @return BelongsTo<RoastBatch, $this> */
    public function roastBatch(): BelongsTo
    {
        return $this->belongsTo(RoastBatch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
