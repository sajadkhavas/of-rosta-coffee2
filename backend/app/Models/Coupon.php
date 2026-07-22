<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Coupon extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'code',
        'type',
        'value',
        'minimum_subtotal',
        'maximum_discount',
        'starts_at',
        'ends_at',
        'max_redemptions',
        'redemption_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'minimum_subtotal' => 'integer',
            'maximum_discount' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'max_redemptions' => 'integer',
            'redemption_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(CheckoutQuote::class);
    }
}
