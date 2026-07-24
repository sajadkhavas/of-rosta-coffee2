<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Exceptions\ApiDomainException;
use App\Http\Requests\Catalog\UpsertProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

final class SellerProductController
{
    public function index(
        Request $request,
        string $roasteryId,
        CatalogAccess $access,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $products = Product::query()
            ->with([
                'origin',
                'primaryImage',
                'gallery',
                'latestRoastBatch',
                'roastery.logo',
                'roastery.cover',
                'variants',
            ])
            ->where('roastery_id', $roastery->id)
            ->orderByDesc('updated_at')
            ->paginate(50);

        return ProductSummaryResource::collection($products);
    }

    public function store(
        UpsertProductRequest $request,
        string $roasteryId,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery, [
            Role::RoasteryOwner,
            Role::RoasteryManager,
        ]);

        $product = DB::transaction(function () use ($request, $roastery, $user, $audit): Product {
            $lockedRoastery = Roastery::query()
                ->lockForUpdate()
                ->findOrFail($roastery->id);

            if ($lockedRoastery->products()->count() >= (int) config('rosta.catalog.max_products_per_roastery')) {
                throw new ApiDomainException(
                    'catalog.product_limit_reached',
                    'سقف محصولات این روستری تکمیل شده است.',
                    409,
                );
            }

            $data = $request->validated();
            $galleryIds = array_values(array_unique($data['gallery_media_ids'] ?? []));
            unset($data['gallery_media_ids']);

            $this->assertMediaOwnership(
                $lockedRoastery,
                $data['primary_media_id'] ?? null,
                $galleryIds,
            );

            $product = Product::query()->create([
                ...$data,
                'roastery_id' => $lockedRoastery->id,
                'status' => $data['status'] ?? ProductStatus::Draft->value,
                'brewing_suggestions' => $data['brewing_suggestions'] ?? [],
                'description' => $data['description'] ?? '',
            ]);

            $product->gallery()->sync($this->gallerySync($galleryIds));

            $audit->record(
                'catalog.product.created',
                actor: $user,
                auditable: $product,
                metadata: ['roastery_id' => $lockedRoastery->id],
                request: $request,
            );

            return $product;
        });

        return (new ProductDetailResource($this->loadProduct($product)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        string $roasteryId,
        string $productId,
        CatalogAccess $access,
    ): ProductDetailResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $product = Product::query()
            ->where('roastery_id', $roastery->id)
            ->findOrFail($productId);

        return new ProductDetailResource($this->loadProduct($product));
    }

    public function update(
        UpsertProductRequest $request,
        string $roasteryId,
        string $productId,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): ProductDetailResource {
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

        DB::transaction(function () use ($request, $roastery, $product, $user, $audit): void {
            $data = $request->validated();
            $galleryProvided = array_key_exists('gallery_media_ids', $data);
            $galleryIds = array_values(array_unique($data['gallery_media_ids'] ?? []));
            unset($data['gallery_media_ids']);

            $this->assertMediaOwnership(
                $roastery,
                $data['primary_media_id'] ?? null,
                $galleryIds,
            );

            $wasPublished = $product->status === ProductStatus::Published;
            $statusProvided = array_key_exists('status', $data);

            $product->fill($data);
            if ($wasPublished && ! $statusProvided) {
                $product->status = ProductStatus::Review;
                $product->published_at = null;
            } elseif ($statusProvided) {
                $product->published_at = null;
            }
            $product->save();

            if ($galleryProvided) {
                $product->gallery()->sync($this->gallerySync($galleryIds));
            }

            $audit->record(
                'catalog.product.updated',
                actor: $user,
                auditable: $product,
                metadata: [
                    'fields' => array_keys($data),
                    'returned_to_review' => $wasPublished && ! $statusProvided,
                ],
                request: $request,
            );
        });

        return new ProductDetailResource($this->loadProduct($product->refresh()));
    }

    /**
     * @param  list<string>  $galleryIds
     */
    private function assertMediaOwnership(
        Roastery $roastery,
        ?string $primaryMediaId,
        array $galleryIds,
    ): void {
        $ids = array_values(array_unique(array_filter([
            $primaryMediaId,
            ...$galleryIds,
        ])));

        if ($ids === []) {
            return;
        }

        $count = MediaAsset::query()
            ->where('roastery_id', $roastery->id)
            ->whereIn('id', $ids)
            ->count();

        if ($count !== count($ids)) {
            abort(404);
        }
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{position: int}>
     */
    private function gallerySync(array $ids): array
    {
        $sync = [];
        foreach ($ids as $position => $id) {
            $sync[$id] = ['position' => $position];
        }

        return $sync;
    }

    private function loadProduct(Product $product): Product
    {
        return $product->load([
            'origin',
            'primaryImage',
            'gallery',
            'latestRoastBatch',
            'roastery.logo',
            'roastery.cover',
            'variants',
        ]);
    }
}
