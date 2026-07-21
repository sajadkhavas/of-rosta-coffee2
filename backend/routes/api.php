<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live'])
        ->name('api.v1.health.live');

    Route::get('/health/ready', [HealthController::class, 'ready'])
        ->name('api.v1.health.ready');
});
