<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Http\Requests\Catalog\StoreRoasteryRequest;
use App\Http\Requests\Catalog\UpdateRoasteryRequest;
use App\Http\Resources\RoasteryDetailResource;
use App\Models\MediaAsset;
use App\Models\Roastery;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SellerRoasteryController
{
    public function store(
        StoreRoasteryRequest $request,
        AuditRecorder $audit,
    ): RoasteryDetailResource {
        /** @var User $user */
        $user = $request->user();

        $roastery = DB::transaction(function () use ($request, $user, $audit): Roastery {
            $roastery = Roastery::query()->create([
                ...$request->validated(),
                'status' => 'pending',
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

        return new RoasteryDetailResource($roastery->load(['logo', 'cover']));
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

        $roastery->fill($data)->save();

        $audit->record(
            'catalog.roastery.updated',
            actor: $user,
            auditable: $roastery,
            metadata: ['fields' => array_keys($data)],
            request: $request,
        );

        return new RoasteryDetailResource($roastery->refresh()->load(['logo', 'cover']));
    }
}
