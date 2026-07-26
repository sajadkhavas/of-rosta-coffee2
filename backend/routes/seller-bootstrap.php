<?php

use App\Http\Controllers\Catalog\GrindingProfileController;
use App\Http\Controllers\Seller\SellerGrindingCapabilityController;
use App\Http\Controllers\Seller\SellerRoasteryController;
use Illuminate\Support\Facades\Route;

Route::get('/grinding-profiles', GrindingProfileController::class)
    ->middleware('throttle:api')
    ->name('api.v1.grinding_profiles.index');

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::get('/seller/roasteries', [SellerRoasteryController::class, 'index'])
        ->middleware('throttle:api')
        ->name('api.v1.seller.roasteries.bootstrap');

    Route::prefix('/seller/roasteries/{roasteryId}')
        ->where(['roasteryId' => '[A-Za-z0-9._:-]+'])
        ->middleware([
            'throttle:api',
            'rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator',
        ])
        ->group(function (): void {
            Route::get('/grinding-capability', [SellerGrindingCapabilityController::class, 'show'])
                ->name('api.v1.seller.grinding_capability.show');
            Route::patch('/grinding-capability', [SellerGrindingCapabilityController::class, 'update'])
                ->name('api.v1.seller.grinding_capability.update');
        });
});
