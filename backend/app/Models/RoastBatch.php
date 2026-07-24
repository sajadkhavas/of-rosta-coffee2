<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $product_id
 * @property string|null $batch_code
 * @property CarbonImmutable $roasted_at
 * @property CarbonImmutable|null $available_from
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product|null $product
 * @property-read Collection<int, StockLedgerEntry> $stockLedgerEntries
 */
final class RoastBatch extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_id',
        'batch_code',
        'roasted_at',
        'available_from',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'roasted_at' => 'immutable_datetime',
            'available_from' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<StockLedgerEntry, $this> */
    public function stockLedgerEntries(): HasMany
    {
        return $this->hasMany(StockLedgerEntry::class);
    }
}
