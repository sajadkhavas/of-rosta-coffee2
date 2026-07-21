<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Services\Sms\DisabledOtpSender;
use App\Services\Sms\LogOtpSender;
use App\Support\IranMobile;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpSender::class, function (): OtpSender {
            if ($this->app->environment(['local', 'testing'])) {
                return new LogOtpSender;
            }

            return new DisabledOtpSender;
        });
    }

    public function boot(): void
    {
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
    }
}
