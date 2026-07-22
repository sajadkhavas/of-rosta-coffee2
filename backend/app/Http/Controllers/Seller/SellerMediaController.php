<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Catalog\CompleteMediaUploadRequest;
use App\Http\Requests\Catalog\CreateMediaUploadRequest;
use App\Http\Requests\Catalog\RegisterMediaRequest;
use App\Http\Resources\MediaAssetResource;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Catalog\MediaRegistrationService;
use App\Services\Catalog\MediaUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
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

    public function createUpload(
        CreateMediaUploadRequest $request,
        string $roasteryId,
        CatalogAccess $access,
        MediaUploadService $uploads,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);
        $result = $uploads->createIntent(
            $roastery,
            $user,
            $request->validated(),
            $request,
        );

        return ApiResponse::success([
            'upload_id' => $result['intent']->id,
            'upload_url' => $result['upload_url'],
            'method' => 'PUT',
            'headers' => $result['headers'],
            'object_key' => $result['intent']->object_key,
            'expires_at' => $result['intent']->expires_at->toIso8601String(),
        ], 201);
    }

    public function completeUpload(
        CompleteMediaUploadRequest $request,
        string $roasteryId,
        string $uploadId,
        CatalogAccess $access,
        MediaUploadService $uploads,
    ): MediaAssetResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        return new MediaAssetResource($uploads->complete(
            $roastery,
            $user,
            $uploadId,
            $request->validated(),
            $request,
        ));
    }
}
