<?php

use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Delivery\DeliveryConfirmationController;
use App\Http\Controllers\Seller\SellerOrderController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/webhooks/carriers/deliveries',
    [DeliveryConfirmationController::class, 'carrier'],
)
    ->middleware('throttle:fulfillment-transition')
    ->name('api.v1.webhooks.carriers.deliveries');

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {

    Route::post(
        '/orders/{orderId}/shipment-legs/{shipmentLegId}/delivery-confirmations',
        [DeliveryConfirmationController::class, 'customer'],
    )
        ->where([
            'orderId' => '[A-Za-z0-9._:-]+',
            'shipmentLegId' => '[A-Za-z0-9._:-]+',
        ])
        ->middleware('throttle:fulfillment-transition')
        ->name('api.v1.orders.delivery_confirmations.store');

    Route::post(
        '/admin/shipment-legs/{shipmentLegId}/delivery-confirmations',
        [DeliveryConfirmationController::class, 'administrator'],
    )
        ->where('shipmentLegId', '[A-Za-z0-9._:-]+')
        ->middleware('throttle:fulfillment-transition')
        ->name('api.v1.admin.delivery_confirmations.store');

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

    Route::post(
        '/seller/roasteries/{roasteryId}/orders/{orderId}/incidents',
        [SellerOrderController::class, 'reportIncident'],
    )
        ->where([
            'roasteryId' => '[A-Za-z0-9._:-]+',
            'orderId' => '[A-Za-z0-9._:-]+',
        ])
        ->middleware('throttle:fulfillment-transition')
        ->name('api.v1.seller.orders.incidents.store');

    Route::get(
        '/admin/fulfillment-incidents',
        [AdminOrderController::class, 'incidents'],
    )
        ->name('api.v1.admin.fulfillment_incidents.index');

    Route::post(
        '/admin/fulfillment-incidents/{incidentId}/resolve',
        [AdminOrderController::class, 'resolveIncident'],
    )
        ->where('incidentId', '[A-Za-z0-9._:-]+')
        ->middleware('throttle:fulfillment-transition')
        ->name('api.v1.admin.fulfillment_incidents.resolve');

    Route::patch(
        '/admin/orders/{orderId}/fulfillment',
        [AdminOrderController::class, 'transition'],
    )
        ->where('orderId', '[A-Za-z0-9._:-]+')
        ->middleware('throttle:fulfillment-transition')
        ->name('api.v1.admin.orders.fulfillment');
});
