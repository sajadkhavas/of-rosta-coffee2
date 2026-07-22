<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\ModerateReviewRequest;
use App\Models\Review;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Reviews\ReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminReviewController extends Controller
{
    public function index(
        Request $request,
        CatalogAccess $access,
        ReviewService $reviews,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $status = trim((string) $request->query('status', 'pending'));
        $query = Review::query()->with(['user', 'product'])->latest('created_at');
        if (in_array($status, array_column(ReviewStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()
                ->map(fn (Review $review): array => $reviews->privatePayload($review))
                ->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function moderate(
        ModerateReviewRequest $request,
        string $reviewId,
        CatalogAccess $access,
        ReviewService $reviews,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $review = Review::query()->findOrFail($reviewId);
        $moderated = $reviews->moderate(
            $user,
            $review,
            ReviewStatus::from((string) $request->validated('status')),
            $request->validated('reason'),
            $request,
        );

        return ApiResponse::success($reviews->privatePayload($moderated));
    }
}
