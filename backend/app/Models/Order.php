<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Order extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'roastery_id',
        'quote_id',
        'order_number',
        'status',
        'address_snapshot',
        'notes',
        'subtotal',
        'shipping_total',
        'discount_total',
        'grand_total',
        'currency',
        'placed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'address_snapshot' => 'encrypted:array',
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'discount_total' => 'integer',
            'grand_total' => 'integer',
            'placed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class, 'quote_id');
    }

    public function subOrder(): HasOne
    {
        return $this->hasOne(SubOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    public function idempotencyKeys(): HasMany
    {
        return $this->hasMany(OrderIdempotencyKey::class);
    }
}
