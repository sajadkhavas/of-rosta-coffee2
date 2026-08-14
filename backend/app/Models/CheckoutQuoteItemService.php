<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $quote_group_id
 * @property string|null $quote_item_id
 * @property string $service_type
 * @property string $provider_type
 * @property string|null $provider_roastery_id
 * @property string|null $provider_hub_id
 * @property string|null $grinding_profile_id
 * @property int $service_fee
 * @property int $packaging_fee
 * @property int $shipping_fee
 * @property int $tax_amount
 * @property int $commission_amount
 * @property int $total_amount
 * @property string $currency
 * @property array<mixed> $pricing_snapshot
 * @property array<mixed> $service_snapshot
 * @property array<mixed> $financial_snapshot
 */
final class CheckoutQuoteItemService extends Model
{
    use HasUlids;

    protected $fillable = [
        'quote_group_id',
        'quote_item_id',
        'service_type',
        'provider_type',
        'provider_roastery_id',
        'provider_hub_id',
        'grinding_profile_id',
        'service_fee',
        'packaging_fee',
        'shipping_fee',
        'tax_amount',
        'commission_amount',
        'total_amount',
        'currency',
        'pricing_snapshot',
        'service_snapshot',
        'financial_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'service_fee' => 'integer',
            'packaging_fee' => 'integer',
            'shipping_fee' => 'integer',
            'tax_amount' => 'integer',
            'commission_amount' => 'integer',
            'total_amount' => 'integer',
            'pricing_snapshot' => 'array',
            'service_snapshot' => 'array',
            'financial_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<CheckoutQuoteGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuoteGroup::class, 'quote_group_id');
    }

    /** @return BelongsTo<CheckoutQuoteItem, $this> */
    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuoteItem::class, 'quote_item_id');
    }

    /** @return BelongsTo<Roastery, $this> */
    public function providerRoastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class, 'provider_roastery_id');
    }

    /** @return BelongsTo<RostaHub, $this> */
    public function providerHub(): BelongsTo
    {
        return $this->belongsTo(RostaHub::class, 'provider_hub_id');
    }

    /** @return BelongsTo<GrindingProfile, $this> */
    public function grindingProfile(): BelongsTo
    {
        return $this->belongsTo(GrindingProfile::class);
    }
}
