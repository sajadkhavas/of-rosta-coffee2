<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\ApiDomainException;
use App\Http\Requests\Catalog\CompleteMediaUploadRequest;
use App\Http\Requests\Catalog\CreateMediaUploadRequest;
use App\Http\Requests\Catalog\RegisterMediaRequest;
use App\Http\Resources\MediaAssetResource;
use App\Models\MediaUploadIntent;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
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
    ): never {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        throw new ApiDomainException(
            'catalog.media_direct_registration_disabled',
            'رسانه فقط از مسیر Upload Intent و پردازش امن ثبت می‌شود.',
            410,
        );
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
            'expires_at' => $result['intent']->expires_at->toIso8601String(),
        ], 201);
    }

    public function completeUpload(
        CompleteMediaUploadRequest $request,
        string $roasteryId,
        string $uploadId,
        CatalogAccess $access,
        MediaUploadService $uploads,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $intent = $uploads->complete(
            $roastery,
            $user,
            $uploadId,
            $request->validated(),
            $request,
        );

        return ApiResponse::success($this->intentPayload($intent, $request), 202);
    }

    public function showUpload(
        Request $request,
        string $roasteryId,
        string $uploadId,
        CatalogAccess $access,
        MediaUploadService $uploads,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        return ApiResponse::success($this->intentPayload(
            $uploads->status($roastery, $user, $uploadId),
            $request,
        ));
    }

    public function retryUpload(
        Request $request,
        string $roasteryId,
        string $uploadId,
        CatalogAccess $access,
        MediaUploadService $uploads,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        return ApiResponse::success($this->intentPayload(
            $uploads->retry($roastery, $user, $uploadId, $request),
            $request,
        ), 202);
    }

    /** @return array<string, mixed> */
    private function intentPayload(MediaUploadIntent $intent, Request $request): array
    {
        $intent->loadMissing('mediaAsset');

        return [
            'upload_id' => $intent->id,
            'status' => $intent->status->value,
            'processing_attempts' => $intent->processing_attempts,
            'retryable' => $intent->failure_retryable,
            'failure_code' => $intent->failure_reason,
            'asset' => $intent->mediaAsset
                ? (new MediaAssetResource($intent->mediaAsset))->resolve($request)
                : null,
            'updated_at' => $intent->updated_at?->toIso8601String(),
        ];
    }
}
