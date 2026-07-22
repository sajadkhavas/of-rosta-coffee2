<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Catalog\RegisterMediaRequest;
use App\Http\Resources\MediaAssetResource;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Catalog\MediaRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SellerMediaController
{
    public function index(
        Request $request,
        string $roasteryId,
        CatalogAccess $access,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        return MediaAssetResource::collection(
            $roastery->mediaAssets()
                ->orderByDesc('created_at')
                ->paginate(50),
        );
    }

    public function store(
        RegisterMediaRequest $request,
        string $roasteryId,
        CatalogAccess $access,
        MediaRegistrationService $media,
    ): MediaAssetResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        return new MediaAssetResource($media->register(
            $roastery,
            $user,
            $request->validated(),
            $request,
        ));
    }
}
