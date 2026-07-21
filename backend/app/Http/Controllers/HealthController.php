<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class HealthController
{
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
        ];

        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
            // Readiness response below remains intentionally generic.
        }

        try {
            $checks['redis'] = Redis::connection()->ping() === true
                || Redis::connection()->ping() === 'PONG';
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
