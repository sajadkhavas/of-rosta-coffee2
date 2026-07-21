<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CheckoutQuoteItem extends Model
{
    use HasUlids;

    protected $fillable = ['quote_id','product_id','variant_id','roast_batch_id','quantity','unit_price','compare_at_price','line_total','product_snapshot','variant_snapshot','roast_batch_snapshot'];

    protected function casts(): array
    {
        return ['quantity' => 'integer','unit_price' => 'integer','compare_at_price' => 'integer','line_total' => 'integer','product_snapshot' => 'array','variant_snapshot' => 'array','roast_batch_snapshot' => 'array'];
    }

    public function quote(): BelongsTo { return $this->belongsTo(CheckoutQuote::class, 'quote_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function roastBatch(): BelongsTo { return $this->belongsTo(RoastBatch::class); }
}
