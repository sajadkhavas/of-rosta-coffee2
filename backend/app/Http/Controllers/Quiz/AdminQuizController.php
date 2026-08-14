<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductSummaryResource;
use App\Models\QuizVersion;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Quiz\QuizService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminQuizController extends Controller
{
    public function index(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */ $user = $request->user(); $access->assertAdministrator($user);
        $items = QuizVersion::query()->orderByDesc('version')->get()->map(fn (QuizVersion $version): array => $this->payload($version))->all();
        return $this->response(['items' => $items]);
    }

    public function show(Request $request, string $versionId, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */ $user = $request->user(); $access->assertAdministrator($user);
        return $this->response($this->payload(QuizVersion::query()->findOrFail($versionId)));
    }

    public function store(Request $request, CatalogAccess $access, QuizService $quiz): JsonResponse
    {
        /** @var User $user */ $user = $request->user(); $access->assertAdministrator($user);
        $input = $request->validate(['title' => ['required', 'string', 'max:160'], 'questions' => ['required', 'array', 'min:1', 'max:20'], 'scoring_profile' => ['required', 'array'], 'recommendation_rules' => ['required', 'array']]);
        return $this->response($this->payload($quiz->adminCreate($user, $input, $request)), 201);
    }

    public function update(Request $request, string $versionId, CatalogAccess $access, QuizService $quiz): JsonResponse
    {
        /** @var User $user */ $user = $request->user(); $access->assertAdministrator($user);
        $input = $request->validate(['title' => ['required', 'string', 'max:160'], 'questions' => ['required', 'array', 'min:1', 'max:20'], 'scoring_profile' => ['required', 'array'], 'recommendation_rules' => ['required', 'array']]);
        return $this->response($this->payload($quiz->adminUpdate($user, QuizVersion::query()->findOrFail($versionId), $input, $request)));
    }

    public function preview(Request $request, string $versionId, CatalogAccess $access, QuizService $quiz): JsonResponse
    {
        /** @var User $user */ $user = $request->user(); $access->assertAdministrator($user);
        $answers = $request->validate(['answers' => ['required', 'array']])['answers'];
        $items = collect($quiz->preview(QuizVersion::query()->findOrFail($versionId), $answers))->map(fn (array $item): array => ['product' => (new ProductSummaryResource($item['product']))->resolve($request), 'score' => $item['score'], 'reasons' => $item['reasons']])->all();
        return $this->response(['items' => $items, 'preview' => true]);
    }

    public function publish(Request $request, string $versionId, CatalogAccess $access, QuizService $quiz): JsonResponse
    {
        /** @var User $user */ $user = $request->user(); $access->assertAdministrator($user);
        return $this->response($this->payload($quiz->publish($user, QuizVersion::query()->findOrFail($versionId), $request)));
    }

    public function archive(Request $request, string $versionId, CatalogAccess $access, QuizService $quiz): JsonResponse
    {
        /** @var User $user */ $user = $request->user(); $access->assertAdministrator($user);
        return $this->response($this->payload($quiz->archive($user, QuizVersion::query()->findOrFail($versionId), $request)));
    }

    private function payload(QuizVersion $version): array
    {
        return ['id' => $version->id, 'version' => $version->version, 'status' => $version->status, 'title' => $version->title, 'questions' => $version->questions, 'scoring_profile' => $version->scoring_profile, 'recommendation_rules' => $version->recommendation_rules, 'checksum' => $version->checksum, 'published_at' => $version->published_at?->toIso8601String(), 'archived_at' => $version->archived_at?->toIso8601String()];
    }

    private function response(mixed $data, int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $status)->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
