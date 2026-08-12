<?php

use App\Http\Controllers\Seller\SellerMediaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::post(
        '/seller/roasteries/{roasteryId}/media/uploads',
        [SellerMediaController::class, 'createUpload'],
    )
        ->where('roasteryId', '[A-Za-z0-9._:-]+')
        ->middleware('throttle:media-upload')
        ->name('api.v1.seller.media.uploads.create');

    Route::post(
        '/seller/roasteries/{roasteryId}/media/uploads/{uploadId}/complete',
        [SellerMediaController::class, 'completeUpload'],
    )
        ->where([
            'roasteryId' => '[A-Za-z0-9._:-]+',
            'uploadId' => '[A-Za-z0-9._:-]+',
        ])
        ->middleware('throttle:media-upload')
        ->name('api.v1.seller.media.uploads.complete');

    Route::get(
        '/seller/roasteries/{roasteryId}/media/uploads/{uploadId}',
        [SellerMediaController::class, 'showUpload'],
    )
        ->where([
            'roasteryId' => '[A-Za-z0-9._:-]+',
            'uploadId' => '[A-Za-z0-9._:-]+',
        ])
        ->middleware('throttle:media-upload')
        ->name('api.v1.seller.media.uploads.show');

    Route::post(
        '/seller/roasteries/{roasteryId}/media/uploads/{uploadId}/retry',
        [SellerMediaController::class, 'retryUpload'],
    )
        ->where([
            'roasteryId' => '[A-Za-z0-9._:-]+',
            'uploadId' => '[A-Za-z0-9._:-]+',
        ])
        ->middleware('throttle:media-upload')
        ->name('api.v1.seller.media.uploads.retry');
});
