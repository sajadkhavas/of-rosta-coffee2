<?php

use App\Http\Controllers\Admin\AdminOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'rosta.session', 'rosta.role:administrator'])
    ->prefix('/admin/operations')
    ->group(function (): void {
        Route::get('/workspace', [AdminOperationsController::class, 'workspace'])
            ->middleware('throttle:admin-operations')
            ->name('api.v1.admin.operations.workspace');
        Route::get('/audits', [AdminOperationsController::class, 'audits'])
            ->middleware('throttle:admin-operations')
            ->name('api.v1.admin.operations.audits');
        Route::get('/notifications', [AdminOperationsController::class, 'notifications'])
            ->middleware('throttle:admin-operations')
            ->name('api.v1.admin.operations.notifications');
    });
