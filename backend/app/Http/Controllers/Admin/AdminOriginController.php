<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Catalog\UpsertOriginRequest;
use App\Http\Resources\OriginResource;
use App\Models\Origin;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminOriginController
{
    public function index(
        Request $request,
        CatalogAccess $access,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return OriginResource::collection(
            Origin::query()
                ->withCount('products')
                ->orderBy('name')
                ->paginate(100),
        );
    }

    public function store(
        UpsertOriginRequest $request,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $data = $request->validated();
        if (isset($data['country_code'])) {
            $data['country_code'] = strtoupper((string) $data['country_code']);
        }

        $origin = Origin::query()->create($data);

        $audit->record(
            'catalog.origin.created',
            actor: $user,
            auditable: $origin,
            request: $request,
        );

        return (new OriginResource($origin))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpsertOriginRequest $request,
        string $originId,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): OriginResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $origin = Origin::query()->findOrFail($originId);
        $data = $request->validated();
        if (array_key_exists('country_code', $data) && $data['country_code'] !== null) {
            $data['country_code'] = strtoupper((string) $data['country_code']);
        }

        $origin->fill($data)->save();

        $audit->record(
            'catalog.origin.updated',
            actor: $user,
            auditable: $origin,
            metadata: ['fields' => array_keys($data)],
            request: $request,
        );

        return new OriginResource($origin->refresh());
    }
}
