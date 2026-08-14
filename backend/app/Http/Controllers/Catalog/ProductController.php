<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Requests\Catalog\ProductIndexRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Product;
use App\Services\Catalog\PublicCatalogService;
use App\Services\Seller\RoasteryAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

final class ProductController
{
    public function index(
        ProductIndexRequest $request,
        PublicCatalogService $catalog,
        RoasteryAvailability $availability,
    ): AnonymousResourceCollection {
        $products = $catalog->products($request->validated());
        $truth = $this->availabilityMap($products->getCollection(), $availability);

        return ProductSummaryResource::collection($products)
            ->additional(['availability' => $truth]);
    }

    public function show(
        string $slug,
        PublicCatalogService $catalog,
        RoasteryAvailability $availability,
    ): ProductDetailResource {
        $product = $catalog->product($slug);
        $resource = new ProductDetailResource($product);
        $resource->additional(['availability' => $availability->snapshot($product->roastery)]);

        return $resource;
    }

    public function related(
        Request $request,
        string $slug,
        PublicCatalogService $catalog,
        RoasteryAvailability $availability,
    ): AnonymousResourceCollection {
        $product = $catalog->product($slug);
        $related = $catalog->related($product);
        $truth = $this->availabilityMap($related, $availability);

        return ProductSummaryResource::collection($related)
            ->additional(['availability' => $truth]);
    }

    /** @param Collection<int, Product> $products */
    private function availabilityMap(Collection $products, RoasteryAvailability $availability): array
    {
        return $products->mapWithKeys(
            static fn (Product $product): array => [
                $product->id => $availability->snapshot($product->roastery),
            ],
        )->all();
    }
}
