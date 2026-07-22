<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\CreateReviewRequest;
use App\Models\User;
use App\Services\Reviews\ReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ReviewController extends Controller
{
    public function store(
        CreateReviewRequest $request,
        ReviewService $reviews,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $review = $reviews->create(
            $user,
            $request->validated(),
            $request,
        );

        return ApiResponse::success($reviews->privatePayload($review), 201);
    }
}
