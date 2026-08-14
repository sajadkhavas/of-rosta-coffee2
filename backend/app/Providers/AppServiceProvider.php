<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\Sms\AcceptanceOtpSender;
use App\Services\Sms\DisabledOtpSender;
use App\Services\Sms\KavenegarOtpSender;
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
            $driver = (string) config('rosta.otp.driver', 'disabled');

            if ($driver === 'acceptance' && $this->app->environment('testing')) {
                return new AcceptanceOtpSender;
            }

            if ($driver === 'log' && $this->app->environment('local')) {
                return new LogOtpSender;
            }

            if ($driver === 'kavenegar') {
                return $this->app->make(KavenegarOtpSender::class);
            }

            return new DisabledOtpSender;
        });
    }

    public function boot(): void
    {
        Order::observe(OrderObserver::class);

        foreach ([
            'payments.php',
            'fulfillment.php',
            'reviews-support.php',
            'media-uploads.php',
            'finance.php',
            'admin-operations.php',
            'seller-bootstrap.php',
            'seller-organization.php',
            'grinding-capability.php',
            'hub-operations.php',
        ] as $routes) {
            Route::prefix('api/v1')
                ->middleware('api')
                ->group(base_path('routes/'.$routes));
        }

        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'ip:'.$request->ip();

            return Limit::perMinute(120)->by($key);
        });

        $acceptanceOtp = $this->app->environment('testing')
            && config('rosta.otp.driver') === 'acceptance';
        $otpRequestsPerMinute = $acceptanceOtp ? 12 : 3;

        RateLimiter::for('otp-request', function (Request $request) use ($otpRequestsPerMinute): array {
            $mobile = IranMobile::normalize((string) $request->input('mobile'));

            return [
                Limit::perMinute($otpRequestsPerMinute)->by('otp-request:ip:'.$request->ip()),
                Limit::perHour(5)->by('otp-request:mobile:'.hash('sha256', $mobile)),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request): array {
            $challengeId = trim((string) $request->input('request_id'));

            return [
                Limit::perMinute(10)->by('otp-verify:ip:'.$request->ip()),
                Limit::perMinute(5)->by('otp-verify:challenge:'.hash('sha256', $challengeId)),
            ];
        });

        RateLimiter::for('cart-validate', fn (Request $request): array => [
            Limit::perMinute(30)->by('cart-validate:ip:'.$request->ip()),
            Limit::perHour(300)->by('cart-validate-hour:ip:'.$request->ip()),
        ]);

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

        RateLimiter::for('payment-callback', fn (Request $request): array => [
            Limit::perMinute(30)->by('payment-callback:ip:'.$request->ip()),
            Limit::perMinute(10)->by('payment-callback:id:'.hash('sha256', (string) $request->route('paymentId'))),
        ]);

        RateLimiter::for('fulfillment-transition', function (Request $request): array {
            $userId = (string) $request->user()?->getAuthIdentifier();
            $orderId = (string) $request->route('orderId');

            return [
                Limit::perMinute(20)->by('fulfillment:user:'.$userId),
                Limit::perMinute(10)->by('fulfillment:order:'.hash('sha256', $orderId)),
            ];
        });

        RateLimiter::for('public-reviews', fn (Request $request): array => [
            Limit::perMinute(60)->by('reviews:ip:'.$request->ip()),
            Limit::perMinute(30)->by('reviews:product:'.hash('sha256', (string) $request->route('productSlug'))),
        ]);

        RateLimiter::for('review-submit', function (Request $request): array {
            $userId = (string) $request->user()?->getAuthIdentifier();

            return [
                Limit::perMinute(5)->by('review-submit:user:'.$userId),
                Limit::perHour(20)->by('review-submit-hour:user:'.$userId),
            ];
        });

        RateLimiter::for('inquiry-submit', function (Request $request): array {
            $contact = trim((string) ($request->input('mobile') ?: $request->input('email')));

            return [
                Limit::perMinute(3)->by('inquiry:ip:'.$request->ip()),
                Limit::perHour(10)->by('inquiry-hour:ip:'.$request->ip()),
                Limit::perHour(5)->by('inquiry:contact:'.hash('sha256', mb_strtolower($contact))),
            ];
        });

        RateLimiter::for('admin-operations', function (Request $request): array {
            $userId = (string) $request->user()?->getAuthIdentifier();

            return [
                Limit::perMinute(60)->by('admin-operations:user:'.$userId),
                Limit::perHour(1000)->by('admin-operations-hour:user:'.$userId),
            ];
        });

        RateLimiter::for('media-upload', function (Request $request): array {
            $userId = (string) $request->user()?->getAuthIdentifier();
            $roasteryId = (string) $request->route('roasteryId');

            return [
                Limit::perMinute(15)->by('media-upload:user:'.$userId),
                Limit::perHour(200)->by('media-upload-hour:roastery:'.hash('sha256', $roasteryId)),
            ];
        });
    }
}
