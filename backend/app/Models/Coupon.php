<?php

namespace App\Models;

use App\Enums\CouponType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $roastery_id
 * @property string|null $code
 * @property CouponType $type
 * @property int $value
 * @property int $minimum_subtotal
 * @property int|null $maximum_discount
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property int|null $max_redemptions
 * @property int $redemption_count
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Roastery|null $roastery
 * @property-read Collection<int, CheckoutQuote> $quotes
 */
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

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return HasMany<CheckoutQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(CheckoutQuote::class);
    }
}
