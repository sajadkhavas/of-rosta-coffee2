<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\RequestOtpRequest;
use App\Services\Identity\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RequestOtpController
{
    public function __invoke(
        RequestOtpRequest $request,
        OtpService $otp,
    ): JsonResponse {
        $challenge = $otp->request(
            $request->string('mobile')->toString(),
            $request->string('purpose')->toString(),
            $request,
        );

        return ApiResponse::success([
            'request_id' => $challenge->id,
            'expires_in' => max(
                0,
                (int) ceil(now()->diffInSeconds($challenge->expires_at, false)),
            ),
            'retry_after' => max(
                0,
                (int) ceil(now()->diffInSeconds(
                    $challenge->resend_available_at,
                    false,
                )),
            ),
        ], 202);
    }
}
