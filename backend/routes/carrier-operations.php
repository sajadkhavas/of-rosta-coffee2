<?php

use App\Http\Controllers\Admin\AdminFailedJobController;
use App\Http\Controllers\Carrier\CarrierOperationsController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/carriers/events', [CarrierOperationsController::class, 'webhook'])
    ->middleware(['rosta.carrier.webhook', 'throttle:carrier-webhook'])
    ->name('api.v1.webhooks.carriers.events');

Route::post('/webhooks/carriers/deliveries', [CarrierOperationsController::class, 'legacyDelivery'])
    ->middleware(['rosta.carrier.webhook', 'throttle:carrier-webhook'])
    ->name('api.v1.webhooks.carriers.deliveries');

Route::middleware(['auth:sanctum', 'rosta.session', 'rosta.role:administrator'])
    ->group(function (): void {
        Route::patch('/admin/shipment-legs/{shipmentLegId}/carrier', [CarrierOperationsController::class, 'manage'])
            ->where('shipmentLegId', '[A-Za-z0-9._:-]+')
            ->middleware('throttle:admin-operations')
            ->name('api.v1.admin.shipment_legs.carrier');

        Route::get('/admin/operations/failed-jobs', [AdminFailedJobController::class, 'index'])
            ->middleware('throttle:admin-operations')
            ->name('api.v1.admin.operations.failed_jobs.index');
        Route::post('/admin/operations/failed-jobs/{uuid}/retry', [AdminFailedJobController::class, 'retry'])
            ->where('uuid', '[A-Za-z0-9-]+')
            ->middleware('throttle:admin-operations')
            ->name('api.v1.admin.operations.failed_jobs.retry');
        Route::post('/admin/operations/failed-jobs/{uuid}/forget-requests', [AdminFailedJobController::class, 'requestForget'])
            ->where('uuid', '[A-Za-z0-9-]+')
            ->middleware('throttle:admin-operations')
            ->name('api.v1.admin.operations.failed_jobs.forget_request');
        Route::post('/admin/operations/failed-job-forget-requests/{operationId}/confirm', [AdminFailedJobController::class, 'confirmForget'])
            ->where('operationId', '[A-Za-z0-9._:-]+')
            ->middleware('throttle:admin-operations')
            ->name('api.v1.admin.operations.failed_jobs.forget_confirm');
    });
