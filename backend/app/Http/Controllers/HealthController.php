<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\OtpSender;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class HealthController
{
    public function __construct(
        private readonly OtpSender $otp,
    ) {}

    public function live(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'service' => 'rosta-api',
            'contract_version' => config('rosta.contract_version'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => false,
            'redis' => false,
            'otp_delivery' => false,
        ];

        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
            // Readiness response below remains intentionally generic.
        }

        $otpEnabled = (bool) config('rosta.otp.enabled', false);
        $otpAvailable = $otpEnabled && $this->otp->isAvailable();
        $checks['otp_delivery'] = app()->environment('production')
            ? $otpAvailable
            : (! $otpEnabled || $otpAvailable);

        try {
            $ping = Redis::connection()->ping();
            $checks['redis'] = $ping === true || strtoupper((string) $ping) === 'PONG';
        } catch (Throwable) {
            // Readiness response below remains intentionally generic.
        }

        $ready = ! in_array(false, $checks, true);

        return ApiResponse::success([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
        ], $ready ? 200 : 503);
    }
}
