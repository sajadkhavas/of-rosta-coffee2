<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\AuthUserResource;
use App\Services\AuditRecorder;
use App\Services\Identity\AuthSessionService;
use App\Services\Identity\OtpService;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class VerifyOtpController
{
    public function __invoke(
        VerifyOtpRequest $request,
        OtpService $otp,
        AuthSessionService $sessions,
        AuditRecorder $audit,
    ): AuthUserResource {
        $user = $otp->verify(
            $request->string('request_id')->toString(),
            $request->string('code')->toString(),
            $request,
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        try {
            $session = $sessions->start($user, $request);
            $audit->record(
                'identity.session.started',
                actor: $user,
                auditable: $session,
                request: $request,
            );
        } catch (Throwable $exception) {
            try {
                $sessions->revokeCurrent($user, $request, 'login_failed');
            } catch (Throwable) {
                // The primary exception remains authoritative.
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw $exception;
        }

        return new AuthUserResource($user);
    }
}
