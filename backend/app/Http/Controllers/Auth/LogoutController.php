<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Identity\AuthSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class LogoutController
{
    public function __invoke(
        Request $request,
        AuthSessionService $sessions,
        AuditRecorder $audit,
    ): Response {
        $user = $request->user();
        if ($user instanceof User) {
            $sessions->revokeCurrent($user, $request);
            $audit->record(
                'identity.session.revoked',
                actor: $user,
                metadata: ['reason' => 'logout'],
                request: $request,
            );
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
