<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Domain bindings are added in later phases without changing the public contract.
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'ip:'.$request->ip();

            return Limit::perMinute(120)->by($key);
        });

        RateLimiter::for('otp-request', fn (Request $request): array => [
            Limit::perMinute(3)->by('otp-request:ip:'.$request->ip()),
            Limit::perHour(5)->by('otp-request:mobile:'.hash('sha256', (string) $request->input('mobile'))),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request): array => [
            Limit::perMinute(10)->by('otp-verify:ip:'.$request->ip()),
            Limit::perMinute(5)->by('otp-verify:challenge:'.hash('sha256', (string) $request->input('request_id'))),
        ]);
    }
}
