<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoasteryStatus;
use App\Http\Requests\Catalog\SetRoasteryStatusRequest;
use App\Http\Resources\RoasteryDetailResource;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Catalog\CatalogPublicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminRoasteryController
{
    public function index(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $query = Roastery::query()->with(['logo', 'cover'])->orderByDesc('updated_at');
        $status = trim((string) $request->query('status', 'pending'));
        if (in_array($status, array_column(RoasteryStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn (Roastery $roastery): array => [
                'id' => $roastery->id,
                'name' => $roastery->name,
                'slug' => $roastery->slug,
                'city' => $roastery->city,
                'description' => $roastery->description ?? '',
                'shipping_policy' => $roastery->shipping_policy,
                'status' => $roastery->status->value,
                'is_verified' => $roastery->isPubliclyVisible(),
                'preparation_time' => $roastery->preparation_min_hours !== null && $roastery->preparation_max_hours !== null
                    ? [
                        'min_hours' => $roastery->preparation_min_hours,
                        'max_hours' => $roastery->preparation_max_hours,
                    ]
                    : null,
                'logo' => $roastery->logo,
                'cover' => $roastery->cover,
                'verified_at' => $roastery->verified_at?->toIso8601String(),
                'updated_at' => $roastery->updated_at?->toIso8601String(),
            ])->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function setStatus(
        SetRoasteryStatusRequest $request,
        string $roasteryId,
        CatalogAccess $access,
        CatalogPublicationService $publication,
    ): RoasteryDetailResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $roastery = Roastery::query()->findOrFail($roasteryId);
        $updated = $publication->setRoasteryStatus(
            $roastery,
            RoasteryStatus::from((string) $request->validated('status')),
            $user,
            $request,
        );

        return new RoasteryDetailResource($updated->load(['logo', 'cover']));
    }
}
