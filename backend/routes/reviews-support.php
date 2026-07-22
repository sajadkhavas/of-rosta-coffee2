<?php

use App\Http\Controllers\Admin\AdminInquiryController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Reviews\PublicReviewController;
use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Support\InquiryController;
use Illuminate\Support\Facades\Route;

Route::get('/products/{productSlug}/reviews', [PublicReviewController::class, 'index'])
    ->where('productSlug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware('throttle:public-reviews')
    ->name('api.v1.products.reviews');

Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware('throttle:inquiry-submit')
    ->name('api.v1.inquiries.store');

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::post('/reviews', [ReviewController::class, 'store'])
        ->middleware('throttle:review-submit')
        ->name('api.v1.reviews.store');

    Route::get('/admin/reviews', [AdminReviewController::class, 'index'])
        ->middleware('throttle:admin-operations')
        ->name('api.v1.admin.reviews.index');
    Route::patch('/admin/reviews/{reviewId}', [AdminReviewController::class, 'moderate'])
        ->where('reviewId', '[A-Za-z0-9._:-]+')
        ->middleware('throttle:admin-operations')
        ->name('api.v1.admin.reviews.moderate');

    Route::get('/admin/inquiries', [AdminInquiryController::class, 'index'])
        ->middleware('throttle:admin-operations')
        ->name('api.v1.admin.inquiries.index');
    Route::get('/admin/inquiries/{inquiryId}', [AdminInquiryController::class, 'show'])
        ->where('inquiryId', '[A-Za-z0-9._:-]+')
        ->middleware('throttle:admin-operations')
        ->name('api.v1.admin.inquiries.show');
    Route::patch('/admin/inquiries/{inquiryId}', [AdminInquiryController::class, 'updateStatus'])
        ->where('inquiryId', '[A-Za-z0-9._:-]+')
        ->middleware('throttle:admin-operations')
        ->name('api.v1.admin.inquiries.update');
});
