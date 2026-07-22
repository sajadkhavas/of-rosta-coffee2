<?php

use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Seller\SellerOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::patch(
        '/seller/roasteries/{roasteryId}/orders/{orderId}/fulfillment',
        [SellerOrderController::class, 'transition'],
    )
        ->where([
            'roasteryId' => '[A-Za-z0-9._:-]+',
            'orderId' => '[A-Za-z0-9._:-]+',
        ])
        ->middleware('throttle:fulfillment-transition')
        ->name('api.v1.seller.orders.fulfillment');

    Route::patch(
        '/admin/orders/{orderId}/fulfillment',
        [AdminOrderController::class, 'transition'],
    )
        ->where('orderId', '[A-Za-z0-9._:-]+')
        ->middleware('throttle:fulfillment-transition')
        ->name('api.v1.admin.orders.fulfillment');
});
