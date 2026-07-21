<?php

namespace App\Models;

use App\Enums\QuotePurpose;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class CheckoutQuote extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'address_id',
        'roastery_id',
        'coupon_id',
        'purpose',
        'payload_hash',
        'subtotal',
        'shipping_total',
        'discount_total',
        'grand_total',
        'currency',
        'address_snapshot',
        'shipping_snapshot',
        'warnings',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => QuotePurpose::class,
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'discount_total' => 'integer',
            'grand_total' => 'integer',
            'address_snapshot' => 'encrypted:array',
            'shipping_snapshot' => 'array',
            'warnings' => 'array',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckoutQuoteItem::class, 'quote_id');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'quote_id');
    }

    public function isUsableFor(User $user): bool
    {
        return $this->purpose === QuotePurpose::Checkout
            && $this->user_id === $user->id
            && $this->consumed_at === null
            && $this->expires_at->isFuture();
    }
}
