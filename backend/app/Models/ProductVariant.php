<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductVariant extends Model
{
    use HasUlids;

    protected $fillable = ['product_id','sku','weight_grams','price','compare_at_price','currency','is_active','stock_on_hand','stock_reserved'];

    protected function casts(): array
    {
        return ['weight_grams' => 'integer','price' => 'integer','compare_at_price' => 'integer','is_active' => 'boolean','stock_on_hand' => 'integer','stock_reserved' => 'integer'];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function stockLedgerEntries(): HasMany { return $this->hasMany(StockLedgerEntry::class, 'variant_id'); }

    public function availableQuantity(): int
    {
        return max(0, $this->stock_on_hand - $this->stock_reserved);
    }
}
