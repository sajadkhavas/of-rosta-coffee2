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
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminProductController
{
    public function index(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $query = Product::query()
            ->with([
                'origin',
                'primaryImage',
                'latestRoastBatch',
                'roastery.logo',
                'roastery.cover',
                'variants',
            ])
            ->orderByDesc('updated_at');
        $status = trim((string) $request->query('status', 'review'));
        if (in_array($status, array_column(ProductStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()
                ->map(fn (Product $product): array => [
                    ...(new ProductSummaryResource($product))->resolve($request),
                    'updated_at' => $product->updated_at?->toIso8601String(),
                ])
                ->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
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
