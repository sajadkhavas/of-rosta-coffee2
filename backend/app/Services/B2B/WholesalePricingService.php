<?php

namespace App\Services\B2B;

use App\Enums\CafeStatus;
use App\Exceptions\ApiDomainException;
use App\Models\Cafe;
use App\Models\CafeMembership;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WholesalePriceTier;

final class WholesalePricingService
{
    private const int MAX_WEIGHT_GRAMS = 10_000_000;

    /**
     * @return array{unit_price:int,snapshot:array<string,mixed>}
     */
    public function resolve(?User $user, ProductVariant $variant, int $quantity): array
    {
        if ($quantity < 1 || $variant->weight_grams < 1) {
            throw new ApiDomainException('b2b.quantity_invalid', 'مقدار سفارش برای قیمت‌گذاری عمده معتبر نیست.', 422);
        }

        if ($quantity > intdiv(self::MAX_WEIGHT_GRAMS, (int) $variant->weight_grams)) {
            throw new ApiDomainException('b2b.weight_out_of_range', 'وزن سفارش از محدوده قیمت‌گذاری عمده خارج است.', 409);
        }

        $totalWeight = (int) $variant->weight_grams * $quantity;
        $cafe = $user instanceof User ? $this->eligibleCafe($user) : null;
        $tier = null;

        if ($cafe instanceof Cafe) {
            $tiers = $variant->relationLoaded('wholesaleTiers')
                ? $variant->wholesaleTiers
                : $variant->wholesaleTiers()->where('is_active', true)->get();

            $tier = $tiers
                ->filter(static fn (WholesalePriceTier $candidate): bool => $candidate->is_active && $candidate->min_weight_grams <= $totalWeight)
                ->sortByDesc('min_weight_grams')
                ->first();
        }

        $unitPrice = $tier instanceof WholesalePriceTier ? (int) $tier->unit_price : (int) $variant->price;
        $snapshot = [
            'version' => 'ps12-wholesale-tier-v1',
            'mode' => $tier instanceof WholesalePriceTier ? 'wholesale' : 'retail',
            'retail_unit_price' => (int) $variant->price,
            'applied_unit_price' => $unitPrice,
            'variant_weight_grams' => (int) $variant->weight_grams,
            'quantity' => $quantity,
            'total_weight_grams' => $totalWeight,
            'cafe_id' => $cafe?->id,
            'tier_id' => $tier?->id,
            'tier_min_weight_grams' => $tier?->min_weight_grams,
        ];

        return ['unit_price' => $unitPrice, 'snapshot' => $snapshot];
    }

    public function eligibleCafe(User $user): ?Cafe
    {
        $membership = CafeMembership::query()
            ->with('cafe')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('cafe', static fn ($query) => $query
                ->where('status', CafeStatus::Verified->value)
                ->whereNotNull('verified_at'))
            ->orderBy('created_at')
            ->first();

        return $membership?->cafe;
    }

    /** @param array<string,mixed> $actual */
    public function snapshotMatches(array $expected, array $actual): bool
    {
        return $expected === $actual;
    }
}
