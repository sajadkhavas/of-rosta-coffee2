<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class OrderItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'product_id',
        'variant_id',
        'roast_batch_id',
        'quantity',
        'unit_price',
        'line_total',
        'product_snapshot',
        'variant_snapshot',
        'roast_batch_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'product_snapshot' => 'array',
            'variant_snapshot' => 'array',
            'roast_batch_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function roastBatch(): BelongsTo
    {
        return $this->belongsTo(RoastBatch::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(InventoryReservation::class);
    }
}
