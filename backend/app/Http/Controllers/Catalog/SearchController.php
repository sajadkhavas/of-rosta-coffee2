<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Requests\Catalog\SearchCatalogRequest;
use App\Http\Resources\ProductSummaryResource;
use App\Http\Resources\RoasterySummaryResource;
use App\Services\Catalog\PublicCatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SearchController
{
    public function __invoke(
        SearchCatalogRequest $request,
        PublicCatalogService $catalog,
    ): JsonResponse {
        $result = $catalog->search(
            trim((string) $request->validated('q')),
            (string) ($request->validated('type') ?? 'all'),
        );

        return ApiResponse::success([
            'products' => ProductSummaryResource::collection($result['products'])
                ->resolve($request),
            'roasteries' => RoasterySummaryResource::collection($result['roasteries'])
                ->resolve($request),
            'suggestions' => $result['suggestions'],
        ]);
    }
}
