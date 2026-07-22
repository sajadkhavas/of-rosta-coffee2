<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryReservation extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'variant_id',
        'roast_batch_id',
        'quantity',
        'status',
        'expires_at',
        'released_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => ReservationStatus::class,
            'expires_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
}
