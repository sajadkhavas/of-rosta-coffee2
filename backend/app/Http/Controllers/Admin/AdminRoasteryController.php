<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoasteryStatus;
use App\Http\Requests\Catalog\SetRoasteryStatusRequest;
use App\Http\Resources\RoasteryDetailResource;
use App\Http\Resources\RoasterySummaryResource;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Catalog\CatalogPublicationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminRoasteryController
{
    public function index(Request $request, CatalogAccess $access): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return RoasterySummaryResource::collection(
            Roastery::query()
                ->with(['logo', 'cover'])
                ->orderByDesc('updated_at')
                ->paginate(50),
        );
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
