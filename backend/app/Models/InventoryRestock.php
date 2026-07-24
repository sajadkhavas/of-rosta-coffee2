<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string|null $order_item_id
 * @property string|null $variant_id
 * @property string|null $roast_batch_id
 * @property string|null $actor_id
 * @property int $quantity
 * @property string|null $reason
 * @property string|null $ledger_entry_id
 * @property CarbonImmutable $restocked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read OrderItem|null $orderItem
 * @property-read ProductVariant|null $variant
 * @property-read RoastBatch|null $roastBatch
 * @property-read User|null $actor
 * @property-read StockLedgerEntry|null $ledgerEntry
 */
final class InventoryRestock extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_item_id',
        'variant_id',
        'roast_batch_id',
        'actor_id',
        'quantity',
        'reason',
        'ledger_entry_id',
        'restocked_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'restocked_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Inventory restock records are append-only.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Inventory restock records are append-only.');
        });
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
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

    /** @return BelongsTo<StockLedgerEntry, $this> */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(StockLedgerEntry::class, 'ledger_entry_id');
    }
}
