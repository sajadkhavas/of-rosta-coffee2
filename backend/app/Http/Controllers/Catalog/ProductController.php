<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Requests\Catalog\ProductIndexRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductSummaryResource;
use App\Services\Catalog\PublicCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductController
{
    public function index(
        ProductIndexRequest $request,
        PublicCatalogService $catalog,
    ): AnonymousResourceCollection {
        return ProductSummaryResource::collection(
            $catalog->products($request->validated()),
        );
    }

    public function show(
        string $slug,
        PublicCatalogService $catalog,
    ): ProductDetailResource {
        return new ProductDetailResource($catalog->product($slug));
    }

    public function related(
        Request $request,
        string $slug,
        PublicCatalogService $catalog,
    ): AnonymousResourceCollection {
        $product = $catalog->product($slug);

        return ProductSummaryResource::collection(
            $catalog->related($product),
        );
    }
}
