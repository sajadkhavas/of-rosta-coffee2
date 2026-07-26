<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Http\Requests\Catalog\UpsertGrindingCapabilityRequest;
use App\Http\Resources\GrindingCapabilityResource;
use App\Models\Roastery;
use App\Models\RoasteryGrindingCapability;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;
use App\Services\Catalog\RoasteryGrindingPolicy;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SellerGrindingCapabilityController
{
    public function show(
        Request $request,
        string $roasteryId,
        CatalogAccess $access,
    ): GrindingCapabilityResource|JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $capability = RoasteryGrindingCapability::query()
            ->with('profiles')
            ->where('roastery_id', $roastery->id)
            ->first();

        return $capability instanceof RoasteryGrindingCapability
            ? new GrindingCapabilityResource($capability)
            : ApiResponse::success(null);
    }

    public function update(
        UpsertGrindingCapabilityRequest $request,
        string $roasteryId,
        CatalogAccess $access,
        RoasteryGrindingPolicy $policy,
        AuditRecorder $audit,
    ): GrindingCapabilityResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery, [
            Role::RoasteryOwner,
            Role::RoasteryManager,
        ]);

        /** @var array{
         *     availability: string,
         *     fee_mode: string,
         *     fee_amount: int,
         *     preparation_minutes: int,
         *     capacity_per_day: int|null,
         *     supported_weights: list<int>,
         *     grinding_profile_ids: list<string>,
         *     is_active: bool
         * } $data
         */
        $data = $request->validated();
        $capability = $policy->upsert($roastery, $data);

        $audit->record(
            'catalog.roastery_grinding_capability.updated',
            actor: $user,
            auditable: $capability,
            metadata: [
                'availability' => $capability->availability->value,
                'fee_mode' => $capability->fee_mode->value,
                'profile_count' => $capability->profiles->count(),
                'supported_weights' => $capability->supported_weights,
                'is_active' => $capability->is_active,
            ],
            request: $request,
        );

        return new GrindingCapabilityResource($capability);
    }
}
