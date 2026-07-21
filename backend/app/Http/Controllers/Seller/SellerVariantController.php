<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Http\Requests\Catalog\UpsertVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;

final class SellerVariantController
{
    public function store(
        UpsertVariantRequest $request,
        string $roasteryId,
        string $productId,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): ProductVariantResource {
        [$user, $product] = $this->context($request, $roasteryId, $productId, $access);

        $variant = ProductVariant::query()->create([
            ...$request->validated(),
            'product_id' => $product->id,
            'currency' => 'IRR',
            'stock_on_hand' => 0,
            'stock_reserved' => 0,
        ]);

        $audit->record(
            'catalog.variant.created',
            actor: $user,
            auditable: $variant,
            request: $request,
        );

        return new ProductVariantResource($variant);
    }

    public function update(
        UpsertVariantRequest $request,
        string $roasteryId,
        string $productId,
        string $variantId,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): ProductVariantResource {
        [$user, $product] = $this->context($request, $roasteryId, $productId, $access);

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->findOrFail($variantId);

        $variant->fill($request->validated())->save();

        $audit->record(
            'catalog.variant.updated',
            actor: $user,
            auditable: $variant,
            metadata: ['fields' => array_keys($request->validated())],
            request: $request,
        );

        return new ProductVariantResource($variant->refresh());
    }

    private function context(
        UpsertVariantRequest $request,
        string $roasteryId,
        string $productId,
        CatalogAccess $access,
    ): array {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery, [
            Role::RoasteryOwner,
            Role::RoasteryManager,
        ]);

        $product = Product::query()
            ->where('roastery_id', $roastery->id)
            ->findOrFail($productId);

        return [$user, $product];
    }
}
