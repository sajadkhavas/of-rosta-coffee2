<?php

namespace App\Models;

use App\Enums\QuotePurpose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $address_id
 * @property string|null $roastery_id
 * @property string|null $coupon_id
 * @property QuotePurpose $purpose
 * @property string|null $payload_hash
 * @property int $subtotal
 * @property int $shipping_total
 * @property int $discount_total
 * @property int $grand_total
 * @property string|null $currency
 * @property array<mixed> $address_snapshot
 * @property array<mixed> $shipping_snapshot
 * @property array<mixed> $warnings
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Address|null $address
 * @property-read Roastery|null $roastery
 * @property-read Coupon|null $coupon
 * @property-read Collection<int, CheckoutQuoteItem> $items
 * @property-read Order|null $order
 */
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Address, $this> */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return HasMany<CheckoutQuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CheckoutQuoteItem::class, 'quote_id');
    }

    /** @return HasOne<Order, $this> */
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
