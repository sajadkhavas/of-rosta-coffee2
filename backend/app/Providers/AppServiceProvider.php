<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Services\Sms\DisabledOtpSender;
use App\Services\Sms\LogOtpSender;
use App\Support\IranMobile;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpSender::class, function (): OtpSender {
            $driver = (string) config('services.sms.driver');

            if (
                $driver === 'log'
                && $this->app->environment(['local', 'testing'])
            ) {
                return new LogOtpSender;
            }

            return new DisabledOtpSender;
        });
    }

    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(base_path('routes/payments.php'));

        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'ip:'.$request->ip();

            return Limit::perMinute(120)->by($key);
        });

        RateLimiter::for('otp-request', function (Request $request): array {
            $mobile = IranMobile::normalize((string) $request->input('mobile'));

            return [
                Limit::perMinute(3)->by('otp-request:ip:'.$request->ip()),
                Limit::perHour(5)->by(
                    'otp-request:mobile:'.hash('sha256', $mobile),
                ),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request): array {
            $challengeId = trim((string) $request->input('request_id'));

            return [
                Limit::perMinute(10)->by('otp-verify:ip:'.$request->ip()),
                Limit::perMinute(5)->by(
                    'otp-verify:challenge:'.hash('sha256', $challengeId),
                ),
            ];
        });

        RateLimiter::for('cart-validate', function (Request $request): array {
            return [
                Limit::perMinute(30)->by('cart-validate:ip:'.$request->ip()),
                Limit::perHour(300)->by('cart-validate-hour:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('checkout-quote', function (Request $request): array {
            $userId = (string) $request->user()?->getAuthIdentifier();

            return [
                Limit::perMinute(20)->by('checkout-quote:user:'.$userId),
                Limit::perHour(200)->by('checkout-quote-hour:user:'.$userId),
            ];
        });

        RateLimiter::for('order-create', function (Request $request): array {
            $userId = (string) $request->user()?->getAuthIdentifier();

            return [
                Limit::perMinute(10)->by('order-create:user:'.$userId),
                Limit::perHour(60)->by('order-create-hour:user:'.$userId),
            ];
        });

        RateLimiter::for('payment-request', function (Request $request): array {
            $userId = (string) $request->user()?->getAuthIdentifier();

            return [
                Limit::perMinute(8)->by('payment-request:user:'.$userId),
                Limit::perHour(40)->by('payment-request-hour:user:'.$userId),
            ];
        });

        RateLimiter::for('payment-verify', function (Request $request): array {
            $userId = (string) $request->user()?->getAuthIdentifier();

            return [
                Limit::perMinute(15)->by('payment-verify:user:'.$userId),
                Limit::perHour(100)->by('payment-verify-hour:user:'.$userId),
            ];
        });

        RateLimiter::for('payment-callback', function (Request $request): array {
            return [
                Limit::perMinute(30)->by('payment-callback:ip:'.$request->ip()),
                Limit::perMinute(10)->by(
                    'payment-callback:id:'.hash('sha256', (string) $request->route('paymentId')),
                ),
            ];
        });
    }
}
