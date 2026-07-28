<?php

use App\Http\Controllers\Admin\AdminHubOperationsController;
use App\Http\Controllers\Hub\OperatorHubOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::prefix('/admin/hub-operations')->middleware('rosta.role:administrator')->group(function (): void {
        Route::get('/work-items', [AdminHubOperationsController::class, 'index'])->name('api.v1.admin.hub.work_items.index');
        Route::get('/operators', [AdminHubOperationsController::class, 'operators'])->name('api.v1.admin.hub.operators.index');
        Route::post('/work-items/{workItemId}/assign', [AdminHubOperationsController::class, 'assign'])->where('workItemId', '[A-Za-z0-9._:-]+')->middleware('throttle:fulfillment-transition')->name('api.v1.admin.hub.work_items.assign');
        Route::post('/work-items/{workItemId}/transition', [AdminHubOperationsController::class, 'transition'])->where('workItemId', '[A-Za-z0-9._:-]+')->middleware('throttle:fulfillment-transition')->name('api.v1.admin.hub.work_items.transition');
    });
    Route::prefix('/hub-operations')->middleware('rosta.role:hub_operator,administrator')->group(function (): void {
        Route::get('/work-items', [OperatorHubOperationsController::class, 'index'])->name('api.v1.hub.work_items.index');
        Route::post('/work-items/{workItemId}/transition', [OperatorHubOperationsController::class, 'transition'])->where('workItemId', '[A-Za-z0-9._:-]+')->middleware('throttle:fulfillment-transition')->name('api.v1.hub.work_items.transition');
    });
});
