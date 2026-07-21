<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Requests\Catalog\SetProductStatusRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Catalog\CatalogPublicationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminProductController
{
    public function index(Request $request, CatalogAccess $access): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return ProductSummaryResource::collection(
            Product::query()
                ->with([
                    'origin',
                    'primaryImage',
                    'latestRoastBatch',
                    'roastery.logo',
                    'roastery.cover',
                    'variants',
                ])
                ->orderByDesc('updated_at')
                ->paginate(50),
        );
    }

    public function setStatus(
        SetProductStatusRequest $request,
        string $productId,
        CatalogAccess $access,
        CatalogPublicationService $publication,
    ): ProductDetailResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $product = Product::query()->findOrFail($productId);
        $updated = $publication->setProductStatus(
            $product,
            ProductStatus::from((string) $request->validated('status')),
            $user,
            $request,
        );

        return new ProductDetailResource($updated->load([
            'origin',
            'primaryImage',
            'gallery',
            'latestRoastBatch',
            'roastery.logo',
            'roastery.cover',
            'variants',
        ]));
    }
}
