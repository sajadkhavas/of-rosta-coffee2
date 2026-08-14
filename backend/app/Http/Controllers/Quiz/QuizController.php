<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductSummaryResource;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Quiz\QuizService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QuizController extends Controller
{
    public function current(QuizService $quiz): JsonResponse
    {
        return ApiResponse::success($quiz->publicVersion($quiz->publishedVersion()))
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function store(Request $request, QuizService $quiz): JsonResponse
    {
        $input = $request->validate([
            'answers' => ['required', 'array'],
            'guest_token' => ['required', 'string', 'min:32', 'max:200'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:120'],
        ]);
        $attempt = $quiz->createGuestAttempt($input['answers'], $input['guest_token'], $input['idempotency_key'], $request);

        return $this->privateResponse([
            'attempt' => $this->attemptPayload($attempt),
            'recommendations' => $this->recommendationPayload($quiz, $attempt, $request),
        ], 201);
    }

    public function showGuest(Request $request, string $attemptId, QuizService $quiz): JsonResponse
    {
        return $this->privateResponse($this->attemptPayload($quiz->guestAttempt($attemptId, $this->guestToken($request))));
    }

    public function recommendationsGuest(Request $request, string $attemptId, QuizService $quiz): JsonResponse
    {
        $attempt = $quiz->guestAttempt($attemptId, $this->guestToken($request));

        return $this->privateResponse($this->recommendationPayload($quiz, $attempt, $request));
    }

    public function destroyGuest(Request $request, string $attemptId, QuizService $quiz): JsonResponse
    {
        $quiz->deleteGuest($attemptId, $this->guestToken($request), $request);

        return $this->privateResponse(['deleted' => true]);
    }

    public function sync(Request $request, string $attemptId, QuizService $quiz): JsonResponse
    {
        $input = $request->validate([
            'guest_token' => ['required', 'string', 'min:32', 'max:200'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:120'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return $this->privateResponse($this->attemptPayload($quiz->sync($user, $attemptId, $input['guest_token'], $input['idempotency_key'], $request)));
    }

    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $items = QuizAttempt::query()->with('version')
            ->where('user_id', $user->id)
            ->latest('completed_at')
            ->limit(50)
            ->get()
            ->map(fn (QuizAttempt $attempt): array => $this->attemptPayload($attempt))
            ->all();

        return $this->privateResponse(['items' => $items]);
    }

    public function destroyOwned(Request $request, string $attemptId, QuizService $quiz): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $quiz->deleteOwned($user, $attemptId, $request);

        return $this->privateResponse(['deleted' => true]);
    }

    private function attemptPayload(QuizAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'version' => $attempt->version?->version,
            'version_checksum' => $attempt->version?->checksum,
            'answers' => $attempt->answers,
            'score_profile' => $attempt->score_profile,
            'synced' => $attempt->user_id !== null,
            'completed_at' => $attempt->completed_at->toIso8601String(),
        ];
    }

    private function recommendationPayload(QuizService $quiz, QuizAttempt $attempt, Request $request): array
    {
        $items = collect($quiz->recommendations($attempt))->map(fn (array $item): array => [
            'product' => (new ProductSummaryResource($item['product']))->resolve($request),
            'score' => $item['score'],
            'reasons' => $item['reasons'],
        ])->all();

        return [
            'items' => $items,
            'catalog_checked_at' => now()->toIso8601String(),
            'stale_safe' => true,
        ];
    }

    private function guestToken(Request $request): string
    {
        $token = trim((string) $request->header('X-Quiz-Guest-Token'));
        if (strlen($token) < 32 || strlen($token) > 200) {
            abort(404);
        }

        return $token;
    }

    private function privateResponse(mixed $data, int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $status)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
