<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Enums\RoasteryStatus;
use App\Http\Requests\Catalog\StoreRoasteryRequest;
use App\Http\Requests\Catalog\UpdateRoasteryRequest;
use App\Http\Resources\RoasteryDetailResource;
use App\Models\MediaAsset;
use App\Models\Roastery;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SellerRoasteryController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roleAssignments');
        $administrator = $user->hasRole(Role::Administrator);
        $sellerRoles = [
            Role::RoasteryOwner->value,
            Role::RoasteryManager->value,
            Role::RoasteryStaff->value,
        ];

        $rolesByRoastery = $user->roleAssignments
            ->filter(static fn (UserRole $assignment): bool =>
                $assignment->scope_type === 'roastery'
                && is_string($assignment->scope_id)
                && in_array($assignment->role->value, $sellerRoles, true)
            )
            ->groupBy('scope_id')
            ->map(static fn ($assignments): array => $assignments
                ->pluck('role')
                ->map(static fn (Role|string $role): string => $role instanceof Role ? $role->value : $role)
                ->unique()
                ->sort()
                ->values()
                ->all()
            );

        if (! $administrator && $rolesByRoastery->isEmpty()) {
            return ApiResponse::success(['items' => []]);
        }

        $query = Roastery::query()
            ->with(['logo', 'cover'])
            ->orderBy('name');
        if (! $administrator) {
            $query->whereIn('id', $rolesByRoastery->keys()->all());
        }

        $items = $query->get()->map(function (Roastery $roastery) use (
            $request,
            $administrator,
            $rolesByRoastery,
        ): array {
            $resource = (new RoasteryDetailResource($roastery))->resolve($request);

            return [
                ...$resource,
                'status' => $roastery->status->value,
                'access_roles' => $administrator
                    ? [Role::Administrator->value]
                    : ($rolesByRoastery->get($roastery->id) ?? []),
            ];
        })->values()->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function store(
        StoreRoasteryRequest $request,
        AuditRecorder $audit,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $roastery = DB::transaction(function () use ($request, $user, $audit): Roastery {
            $roastery = Roastery::query()->create([
                ...$request->validated(),
                'status' => RoasteryStatus::Pending->value,
            ]);

            UserRole::query()->firstOrCreate([
                'user_id' => $user->id,
                'role' => Role::RoasteryOwner->value,
                'scope_type' => 'roastery',
                'scope_id' => $roastery->id,
            ]);

            $audit->record(
                'catalog.roastery.created',
                actor: $user,
                auditable: $roastery,
                request: $request,
            );

            return $roastery;
        });

        return (new RoasteryDetailResource($roastery->load(['logo', 'cover'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        string $roasteryId,
        CatalogAccess $access,
    ): RoasteryDetailResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->with(['logo', 'cover'])->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        return new RoasteryDetailResource($roastery);
    }

    public function update(
        UpdateRoasteryRequest $request,
        string $roasteryId,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): RoasteryDetailResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery, [
            Role::RoasteryOwner,
            Role::RoasteryManager,
        ]);

        $data = $request->validated();

        foreach (['logo_media_id', 'cover_media_id'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            MediaAsset::query()
                ->where('roastery_id', $roastery->id)
                ->findOrFail($data[$field]);
        }

        $verificationSensitiveFields = ['name', 'slug', 'city', 'description'];
        $requiresReview = $roastery->status === RoasteryStatus::Verified
            && array_intersect($verificationSensitiveFields, array_keys($data)) !== [];

        $roastery->fill($data);
        if ($requiresReview) {
            $roastery->status = RoasteryStatus::Pending;
            $roastery->verified_at = null;
        }
        $roastery->save();

        $audit->record(
            'catalog.roastery.updated',
            actor: $user,
            auditable: $roastery,
            metadata: [
                'fields' => array_keys($data),
                'verification_reset' => $requiresReview,
            ],
            request: $request,
        );

        return new RoasteryDetailResource($roastery->refresh()->load(['logo', 'cover']));
    }
}
