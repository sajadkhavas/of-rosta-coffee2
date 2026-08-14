<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class QuizReviewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/v1')->middleware('api')->group(base_path('routes/quiz-reviews.php'));

        RateLimiter::for('quiz-submit', function (Request $request): array {
            $token = (string) $request->input('guest_token');
            return [
                Limit::perMinute(12)->by('quiz:ip:'.$request->ip()),
                Limit::perMinute(6)->by('quiz:guest:'.hash('sha256', $token)),
            ];
        });

        RateLimiter::for('review-report', function (Request $request): array {
            $userId = (string) $request->user()?->getAuthIdentifier();
            return [
                Limit::perMinute(5)->by('review-report:user:'.$userId),
                Limit::perHour(20)->by('review-report-hour:user:'.$userId),
            ];
        });
    }
}
