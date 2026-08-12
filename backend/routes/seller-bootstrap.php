<?php

use App\Http\Controllers\Seller\SellerRoasteryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::get('/seller/roasteries', [SellerRoasteryController::class, 'index'])
        ->middleware([
            'throttle:api',
            'rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator',
        ])
        ->name('api.v1.seller.roasteries.bootstrap');
});
