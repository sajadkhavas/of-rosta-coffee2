<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RequestOtpController;
use App\Http\Controllers\Auth\VerifyOtpController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Profile\AddressController;
use App\Http\Controllers\Profile\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live'])
        ->name('api.v1.health.live');

    Route::get('/health/ready', [HealthController::class, 'ready'])
        ->name('api.v1.health.ready');

    Route::post('/auth/otp/request', RequestOtpController::class)
        ->middleware('throttle:otp-request')
        ->name('api.v1.auth.otp.request');

    Route::post('/auth/otp/verify', VerifyOtpController::class)
        ->middleware('throttle:otp-verify')
        ->name('api.v1.auth.otp.verify');

    Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
        Route::post('/auth/logout', LogoutController::class)
            ->name('api.v1.auth.logout');

        Route::get('/me', [MeController::class, 'show'])
            ->name('api.v1.me.show');
        Route::patch('/me', [MeController::class, 'update'])
            ->name('api.v1.me.update');

        Route::get('/me/addresses', [AddressController::class, 'index'])
            ->name('api.v1.addresses.index');
        Route::post('/me/addresses', [AddressController::class, 'store'])
            ->name('api.v1.addresses.store');
        Route::patch('/me/addresses/{addressId}', [AddressController::class, 'update'])
            ->where('addressId', '[A-Za-z0-9._:-]+')
            ->name('api.v1.addresses.update');
        Route::delete('/me/addresses/{addressId}', [AddressController::class, 'destroy'])
            ->where('addressId', '[A-Za-z0-9._:-]+')
            ->name('api.v1.addresses.destroy');
    });
});
