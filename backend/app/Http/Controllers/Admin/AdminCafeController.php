<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CafeStatus;
use App\Enums\Role;
use App\Http\Requests\Cafe\SetCafeStatusRequest;
use App\Http\Resources\CafeResource;
use App\Models\Cafe;
use App\Models\User;
use App\Services\Cafe\CafeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminCafeController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasRole(Role::Administrator)) {
            abort(403);
        }
        $status = trim((string) $request->query('status', CafeStatus::Pending->value));
        $query = Cafe::query()->orderByDesc('updated_at');
        if (in_array($status, array_column(CafeStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $page = $query->paginate(perPage: max(1, min(100, (int) $request->query('per_page', 50))), page: max(1, (int) $request->query('page', 1)));

        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn (Cafe $cafe): array => (new CafeResource($cafe))->resolve($request))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function setStatus(SetCafeStatusRequest $request, string $cafeId, CafeService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasRole(Role::Administrator)) {
            abort(403);
        }
        $cafe = Cafe::query()->findOrFail($cafeId);
        $updated = $service->setStatus($cafe, CafeStatus::from((string) $request->validated('status')), $user, $request->input('review_note'), $request);

        return ApiResponse::success((new CafeResource($updated))->resolve($request));
    }
}
