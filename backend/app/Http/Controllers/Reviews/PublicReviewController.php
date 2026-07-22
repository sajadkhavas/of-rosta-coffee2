<?php

namespace App\Http\Controllers\Reviews;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Reviews\ReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicReviewController extends Controller
{
    public function index(
        Request $request,
        string $productSlug,
        ReviewService $reviews,
    ): JsonResponse {
        $product = Product::query()
            ->where('slug', $productSlug)
            ->where('status', ProductStatus::Published->value)
            ->whereNotNull('published_at')
            ->firstOrFail();
        $limit = max(1, min(100, (int) $request->query('limit', 20)));

        return ApiResponse::success($reviews->publicForProduct($product, $limit));
    }
}
