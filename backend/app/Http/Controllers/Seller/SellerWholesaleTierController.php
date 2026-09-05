<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Http\Requests\Catalog\ReplaceWholesaleTiersRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\User;
use App\Models\WholesalePriceTier;
use App\Services\AuditRecorder;
use App\Services\B2B\WholesaleTierService;
use App\Services\Catalog\CatalogAccess;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SellerWholesaleTierController
{
    public function index(Request $request, string $roasteryId, string $productId, string $variantId, CatalogAccess $access): JsonResponse
    {
        [, $variant] = $this->context($request, $roasteryId, $productId, $variantId, $access);

        return ApiResponse::success(['items' => $this->tiers($variant->load('wholesaleTiers'))]);
    }

    public function replace(ReplaceWholesaleTiersRequest $request, string $roasteryId, string $productId, string $variantId, CatalogAccess $access, WholesaleTierService $tiers, AuditRecorder $audit): JsonResponse
    {
        [$user, $variant] = $this->context($request, $roasteryId, $productId, $variantId, $access);
        $updated = $tiers->replace($variant, $request->validated('tiers'));
        $audit->record('catalog.wholesale_tiers.replaced', actor: $user, auditable: $updated, metadata: ['thresholds' => $updated->wholesaleTiers->pluck('min_weight_grams')->all()], request: $request);

        return ApiResponse::success(['items' => $this->tiers($updated)]);
    }

    /** @return array{User,ProductVariant} */
    private function context(Request $request, string $roasteryId, string $productId, string $variantId, CatalogAccess $access): array
    {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery, [Role::RoasteryOwner, Role::RoasteryManager]);
        $product = Product::query()->where('roastery_id', $roastery->id)->findOrFail($productId);
        $variant = ProductVariant::query()->where('product_id', $product->id)->findOrFail($variantId);

        return [$user, $variant];
    }

    /** @return list<array{id:string,min_weight_grams:int,unit_price:int,is_active:bool}> */
    private function tiers(ProductVariant $variant): array
    {
        return $variant->wholesaleTiers
            ->sortBy('min_weight_grams')
            ->values()
            ->map(static fn (WholesalePriceTier $tier): array => [
                'id' => $tier->id,
                'min_weight_grams' => $tier->min_weight_grams,
                'unit_price' => $tier->unit_price,
                'is_active' => $tier->is_active,
            ])
            ->all();
    }
}
