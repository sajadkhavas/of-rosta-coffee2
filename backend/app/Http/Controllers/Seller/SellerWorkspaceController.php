<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Seller\SellerAccess;
use App\Services\Workspace\WorkspaceKpiService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SellerWorkspaceController extends Controller
{
    public function index(
        Request $request,
        SellerAccess $sellerAccess,
        WorkspaceKpiService $workspaceKpis,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $administrator = $user->hasRole(Role::Administrator);
        $rolesByRoastery = collect($sellerAccess->rolesByRoastery($user));

        if (! $administrator && $rolesByRoastery->isEmpty()) {
            return ApiResponse::success([
                'items' => [],
                'generated_at' => now()->toImmutable()->toIso8601String(),
            ]);
        }

        $query = Roastery::query()->orderBy('name');
        if (! $administrator) {
            $query->whereIn('id', $rolesByRoastery->keys()->all());
        }

        $items = $query->get()->map(fn (Roastery $roastery): array => [
            'roastery' => [
                'id' => $roastery->id,
                'name' => $roastery->name,
                'status' => $roastery->status->value,
            ],
            'access_roles' => $administrator
                ? [Role::Administrator->value]
                : ($rolesByRoastery->get($roastery->id) ?? []),
            'kpis' => $workspaceKpis->seller($roastery),
        ])->values()->all();

        return ApiResponse::success([
            'items' => $items,
            'generated_at' => now()->toImmutable()->toIso8601String(),
        ]);
    }
}
