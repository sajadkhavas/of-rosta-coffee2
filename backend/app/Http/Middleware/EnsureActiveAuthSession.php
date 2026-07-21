<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Identity\AuthSessionService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveAuthSession
{
    public function __construct(
        private readonly AuthSessionService $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $session = $this->sessions->current($user, $request);
        if (! $session || ! $session->isActive()) {
            if ($session && $session->revoked_at === null) {
                $session->forceFill([
                    'revoked_at' => now(),
                    'revoke_reason' => 'inactive_request',
                ])->save();
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException;
        }

        $this->sessions->touch($session);

        return $next($request);
    }
}
