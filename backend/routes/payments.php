<?php

use App\Http\Controllers\Payments\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/payments/testing/callback/{paymentId}', [PaymentController::class, 'callback'])
    ->where('paymentId', '[A-Za-z0-9._:-]+')
    ->middleware('throttle:payment-callback')
    ->name('api.v1.payments.testing.callback');

Route::get('/payments/zarinpal/callback/{paymentId}', [PaymentController::class, 'callback'])
    ->where('paymentId', '[A-Za-z0-9._:-]+')
    ->middleware('throttle:payment-callback')
    ->name('api.v1.payments.zarinpal.callback');

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::post('/payments/request', [PaymentController::class, 'request'])
        ->middleware('throttle:payment-request')
        ->name('api.v1.payments.request');

    Route::post('/payments/{paymentId}/verify', [PaymentController::class, 'verify'])
        ->where('paymentId', '[A-Za-z0-9._:-]+')
        ->middleware('throttle:payment-verify')
        ->name('api.v1.payments.verify');
});
