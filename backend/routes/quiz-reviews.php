<?php

use App\Http\Controllers\Quiz\AdminQuizController;
use App\Http\Controllers\Quiz\QuizController;
use App\Http\Controllers\Reviews\ReviewSafetyController;
use Illuminate\Support\Facades\Route;

Route::get('/quiz/current', [QuizController::class, 'current'])->name('api.v1.quiz.current');
Route::post('/quiz/attempts', [QuizController::class, 'store'])->middleware('throttle:quiz-submit')->name('api.v1.quiz.attempts.store');
Route::get('/quiz/attempts/{attemptId}', [QuizController::class, 'showGuest'])->where('attemptId', '[A-Za-z0-9._:-]+')->name('api.v1.quiz.attempts.show');
Route::get('/quiz/attempts/{attemptId}/recommendations', [QuizController::class, 'recommendationsGuest'])->where('attemptId', '[A-Za-z0-9._:-]+')->name('api.v1.quiz.attempts.recommendations');
Route::delete('/quiz/attempts/{attemptId}', [QuizController::class, 'destroyGuest'])->where('attemptId', '[A-Za-z0-9._:-]+')->name('api.v1.quiz.attempts.destroy');

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::post('/quiz/attempts/{attemptId}/sync', [QuizController::class, 'sync'])->where('attemptId', '[A-Za-z0-9._:-]+')->middleware('rosta.role:customer')->name('api.v1.quiz.attempts.sync');
    Route::get('/me/quiz-attempts', [QuizController::class, 'profile'])->middleware('rosta.role:customer')->name('api.v1.me.quiz.index');
    Route::delete('/me/quiz-attempts/{attemptId}', [QuizController::class, 'destroyOwned'])->where('attemptId', '[A-Za-z0-9._:-]+')->middleware('rosta.role:customer')->name('api.v1.me.quiz.destroy');
    Route::post('/reviews/{reviewId}/reports', [ReviewSafetyController::class, 'report'])->where('reviewId', '[A-Za-z0-9._:-]+')->middleware(['rosta.role:customer', 'throttle:review-report'])->name('api.v1.reviews.reports.store');

    Route::prefix('/seller/roasteries/{roasteryId}')->middleware('rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator')->group(function (): void {
        Route::get('/reviews', [ReviewSafetyController::class, 'sellerIndex'])->name('api.v1.seller.reviews.index');
        Route::put('/reviews/{reviewId}/reply', [ReviewSafetyController::class, 'upsertReply'])->where('reviewId', '[A-Za-z0-9._:-]+')->name('api.v1.seller.reviews.reply');
    });

    Route::prefix('/admin')->middleware(['rosta.role:administrator', 'throttle:admin-operations'])->group(function (): void {
        Route::get('/quiz/versions', [AdminQuizController::class, 'index'])->name('api.v1.admin.quiz.index');
        Route::post('/quiz/versions', [AdminQuizController::class, 'store'])->name('api.v1.admin.quiz.store');
        Route::get('/quiz/versions/{versionId}', [AdminQuizController::class, 'show'])->where('versionId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.quiz.show');
        Route::patch('/quiz/versions/{versionId}', [AdminQuizController::class, 'update'])->where('versionId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.quiz.update');
        Route::post('/quiz/versions/{versionId}/preview', [AdminQuizController::class, 'preview'])->where('versionId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.quiz.preview');
        Route::post('/quiz/versions/{versionId}/publish', [AdminQuizController::class, 'publish'])->where('versionId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.quiz.publish');
        Route::post('/quiz/versions/{versionId}/archive', [AdminQuizController::class, 'archive'])->where('versionId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.quiz.archive');
        Route::get('/review-reports', [ReviewSafetyController::class, 'adminReports'])->name('api.v1.admin.review-reports.index');
        Route::patch('/review-reports/{reportId}', [ReviewSafetyController::class, 'moderateReport'])->where('reportId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.review-reports.update');
        Route::get('/review-replies', [ReviewSafetyController::class, 'adminReplies'])->name('api.v1.admin.review-replies.index');
        Route::patch('/review-replies/{replyId}', [ReviewSafetyController::class, 'moderateReply'])->where('replyId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.review-replies.update');
    });
});
