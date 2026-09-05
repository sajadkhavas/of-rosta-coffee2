<?php

namespace App\Services\B2B;

use App\Exceptions\ApiDomainException;
use App\Models\ProductVariant;
use App\Models\WholesalePriceTier;
use Illuminate\Support\Facades\DB;

final class WholesaleTierService
{
    public const array DEFAULT_THRESHOLDS = [5_000, 10_000, 20_000, 50_000];

    /**
     * @param  list<array{min_weight_grams:int,unit_price:int,is_active?:bool}>  $tiers
     */
    public function replace(ProductVariant $variant, array $tiers): ProductVariant
    {
        $seen = [];
        $ordered = collect($tiers)->sortBy('min_weight_grams')->values();
        $previousPrice = (int) $variant->price;

        foreach ($ordered as $tier) {
            $threshold = (int) $tier['min_weight_grams'];
            $price = (int) $tier['unit_price'];
            if (! in_array($threshold, self::DEFAULT_THRESHOLDS, true) || isset($seen[$threshold])) {
                throw new ApiDomainException('b2b.wholesale_threshold_invalid', 'پله وزن عمده معتبر یا یکتا نیست.', 422);
            }
            if ($price <= 0 || $price > (int) $variant->price || $price > $previousPrice) {
                throw new ApiDomainException('b2b.wholesale_price_invalid', 'قیمت عمده باید مثبت، حداکثر قیمت تک و با افزایش وزن نزولی یا ثابت باشد.', 422);
            }
            $seen[$threshold] = true;
            $previousPrice = $price;
        }

        return DB::transaction(function () use ($variant, $ordered): ProductVariant {
            WholesalePriceTier::query()->where('product_variant_id', $variant->id)->delete();
            foreach ($ordered as $tier) {
                WholesalePriceTier::query()->create([
                    'product_variant_id' => $variant->id,
                    'min_weight_grams' => (int) $tier['min_weight_grams'],
                    'unit_price' => (int) $tier['unit_price'],
                    'is_active' => (bool) ($tier['is_active'] ?? true),
                ]);
            }

            return $variant->refresh()->load(['wholesaleTiers' => static fn ($query) => $query->orderBy('min_weight_grams')]);
        });
    }
}
