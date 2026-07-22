<?php

namespace App\Models;

use App\Enums\RoasteryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Roastery extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'city',
        'description',
        'shipping_policy',
        'status',
        'verified_at',
        'preparation_min_hours',
        'preparation_max_hours',
        'rating_value',
        'rating_count',
        'logo_media_id',
        'cover_media_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoasteryStatus::class,
            'verified_at' => 'immutable_datetime',
            'rating_value' => 'float',
            'rating_count' => 'integer',
            'preparation_min_hours' => 'integer',
            'preparation_max_hours' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function shippingRules(): HasMany
    {
        return $this->hasMany(ShippingRule::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function checkoutQuotes(): HasMany
    {
        return $this->hasMany(CheckoutQuote::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === RoasteryStatus::Verified && $this->verified_at !== null;
    }
}
