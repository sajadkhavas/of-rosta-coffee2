<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Workspace\WorkspaceKpiService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SellerWorkspaceController extends Controller
{
    public function show(
        Request $request,
        string $roasteryId,
        CatalogAccess $access,
        WorkspaceKpiService $workspaceKpis,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        return ApiResponse::success([
            'roastery_id' => $roastery->id,
            'kpis' => $workspaceKpis->seller($roastery),
            'generated_at' => now()->toImmutable()->toIso8601String(),
        ]);
    }
}
