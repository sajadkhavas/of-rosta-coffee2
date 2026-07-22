<?php

namespace App\Models;

use App\Enums\SubOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SubOrder extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'roastery_id',
        'status',
        'subtotal',
        'shipping_total',
        'accepted_at',
        'rejected_at',
        'preparing_at',
        'ready_to_ship_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubOrderStatus::class,
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'accepted_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'preparing_at' => 'immutable_datetime',
            'ready_to_ship_at' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(SubOrderStatusHistory::class)
            ->orderBy('created_at');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(OrderInternalNote::class);
    }
}
