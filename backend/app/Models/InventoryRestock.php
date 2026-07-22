<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

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
        static::updating(static function (): never {
            throw new LogicException('Inventory restock records are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Inventory restock records are append-only.');
        });
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
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

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(StockLedgerEntry::class, 'ledger_entry_id');
    }
}
