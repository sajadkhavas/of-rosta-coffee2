<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\ReviewReport;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Reviews\ReviewSafetyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReviewSafetyController extends Controller
{
    public function sellerIndex(Request $request, string $roasteryId, ReviewSafetyService $safety): JsonResponse
    {
        /** @var User $user */ $user = $request->user();

        return $this->response(['items' => $safety->sellerReviews($user, Roastery::query()->findOrFail($roasteryId), (int) $request->query('limit', 50))]);
    }

    public function upsertReply(Request $request, string $roasteryId, string $reviewId, ReviewSafetyService $safety): JsonResponse
    {
        $body = $request->validate(['body' => ['required', 'string', 'min:1', 'max:5000']])['body'];
        /** @var User $user */ $user = $request->user();
        $reply = $safety->upsertReply($user, Roastery::query()->findOrFail($roasteryId), Review::query()->findOrFail($reviewId), $body, $request);

        return $this->response($safety->replyPayload($reply));
    }

    public function report(Request $request, string $reviewId, ReviewSafetyService $safety): JsonResponse
    {
        $input = $request->validate(['reason' => ['required', 'string', 'max:32'], 'evidence' => ['nullable', 'string', 'max:500']]);
        /** @var User $user */ $user = $request->user();
        $report = $safety->report($user, Review::query()->findOrFail($reviewId), $input['reason'], $input['evidence'] ?? null, $request);

        return $this->response($safety->reportPayload($report), 201);
    }

    public function adminReports(Request $request, CatalogAccess $access, ReviewSafetyService $safety): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $access->assertAdministrator($user);
        $status = $request->validate(['status' => ['nullable', 'in:open,reviewing,resolved,dismissed']])['status'] ?? 'open';
        $items = ReviewReport::query()->where('status', $status)->latest()->limit(100)->get()->map(fn (ReviewReport $report): array => $safety->reportPayload($report))->all();

        return $this->response(['items' => $items]);
    }

    public function adminReplies(Request $request, CatalogAccess $access, ReviewSafetyService $safety): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $access->assertAdministrator($user);
        $status = $request->validate(['status' => ['nullable', 'in:visible,hidden,rejected']])['status'] ?? 'visible';
        $items = ReviewReply::query()->where('status', $status)->latest()->limit(100)->get()->map(fn (ReviewReply $reply): array => $safety->replyPayload($reply))->all();

        return $this->response(['items' => $items]);
    }

    public function moderateReport(Request $request, string $reportId, ReviewSafetyService $safety): JsonResponse
    {
        $input = $request->validate(['status' => ['required', 'string', 'max:24'], 'resolution_reason' => ['nullable', 'string', 'max:500']]);
        /** @var User $user */ $user = $request->user();

        return $this->response($safety->reportPayload($safety->moderateReport($user, ReviewReport::query()->findOrFail($reportId), $input['status'], $input['resolution_reason'] ?? null, $request)));
    }

    public function moderateReply(Request $request, string $replyId, ReviewSafetyService $safety): JsonResponse
    {
        $input = $request->validate(['status' => ['required', 'string', 'max:24'], 'reason' => ['nullable', 'string', 'max:500']]);
        /** @var User $user */ $user = $request->user();

        return $this->response($safety->replyPayload($safety->moderateReply($user, ReviewReply::query()->findOrFail($replyId), $input['status'], $input['reason'] ?? null, $request)));
    }

    private function response(mixed $data, int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $status)->header('Cache-Control', 'private, no-store, max-age=0')->header('Pragma', 'no-cache');
    }
}
