<?php

namespace App\Models;

use App\Enums\RoasteryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $city
 * @property string|null $description
 * @property string|null $shipping_policy
 * @property RoasteryStatus $status
 * @property CarbonImmutable|null $verified_at
 * @property int|null $preparation_min_hours
 * @property int|null $preparation_max_hours
 * @property float|null $rating_value
 * @property int $rating_count
 * @property string|null $logo_media_id
 * @property string|null $cover_media_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, MediaAsset> $mediaAssets
 * @property-read Collection<int, ShippingRule> $shippingRules
 * @property-read Collection<int, Coupon> $coupons
 * @property-read Collection<int, CheckoutQuote> $checkoutQuotes
 * @property-read Collection<int, CheckoutQuoteGroup> $checkoutQuoteGroups
 * @property-read Collection<int, Order> $orders
 * @property-read Collection<int, SubOrder> $subOrders
 * @property-read RoasteryGrindingCapability|null $grindingCapability
 * @property-read MediaAsset|null $logo
 * @property-read MediaAsset|null $cover
 */
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

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<MediaAsset, $this> */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    /** @return HasMany<ShippingRule, $this> */
    public function shippingRules(): HasMany
    {
        return $this->hasMany(ShippingRule::class);
    }

    /** @return HasMany<Coupon, $this> */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /** @return HasMany<CheckoutQuote, $this> */
    public function checkoutQuotes(): HasMany
    {
        return $this->hasMany(CheckoutQuote::class);
    }

    /** @return HasMany<CheckoutQuoteGroup, $this> */
    public function checkoutQuoteGroups(): HasMany
    {
        return $this->hasMany(CheckoutQuoteGroup::class);
    }

    /**
     * Legacy single-roastery parent-order relation.
     *
     * Marketplace settlement and fulfilment must use subOrders().
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<SubOrder, $this> */
    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    /** @return HasOne<RoasteryGrindingCapability, $this> */
    public function grindingCapability(): HasOne
    {
        return $this->hasOne(RoasteryGrindingCapability::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === RoasteryStatus::Verified && $this->verified_at !== null;
    }
}
