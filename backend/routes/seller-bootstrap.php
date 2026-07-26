<?php

use App\Http\Controllers\Seller\SellerRoasteryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::get('/seller/roasteries', [SellerRoasteryController::class, 'index'])
        ->middleware('throttle:api')
        ->name('api.v1.seller.roasteries.bootstrap');
});
