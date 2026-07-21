<?php

namespace App\Services\Identity;

use App\Models\AuthSession;
use App\Models\User;
use App\Support\RequestFingerprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AuthSessionService
{
    public const SESSION_KEY = 'rosta_auth_session_id';

    public function start(User $user, Request $request): AuthSession
    {
        $session = DB::transaction(function () use ($user, $request): AuthSession {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            AuthSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '<=', now())
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'expired',
                    'updated_at' => now(),
                ]);

            $created = AuthSession::query()->create([
                'user_id' => $user->id,
                'ip_hash' => RequestFingerprint::ip($request),
                'user_agent_hash' => RequestFingerprint::userAgent($request),
                'last_seen_at' => now(),
                'expires_at' => now()->addMinutes(
                    (int) config('rosta.auth_sessions.ttl_minutes', 120),
                ),
            ]);

            $maximum = (int) config('rosta.auth_sessions.max_active_per_user', 5);
            $overflow = AuthSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->skip($maximum)
                ->take(100)
                ->pluck('id');

            if ($overflow->isNotEmpty()) {
                AuthSession::query()
                    ->whereIn('id', $overflow)
                    ->update([
                        'revoked_at' => now(),
                        'revoke_reason' => 'active_session_limit',
                        'updated_at' => now(),
                    ]);
            }

            return $created;
        }, 3);

        $request->session()->put(self::SESSION_KEY, $session->id);

        return $session;
    }

    public function current(User $user, Request $request): ?AuthSession
    {
        $sessionId = $request->session()->get(self::SESSION_KEY);
        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        return AuthSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $user->id)
            ->first();
    }

    public function touch(AuthSession $session): void
    {
        if ($session->last_seen_at?->gt(now()->subMinutes(5))) {
            return;
        }

        $session->forceFill(['last_seen_at' => now()])->save();
    }

    public function revokeCurrent(
        User $user,
        Request $request,
        string $reason = 'logout',
    ): void {
        $session = $this->current($user, $request);
        if ($session && $session->revoked_at === null) {
            $session->forceFill([
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ])->save();
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    public function revokeAll(User $user, string $reason = 'revoke_all'): int
    {
        return AuthSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => $reason,
                'updated_at' => now(),
            ]);
    }
}
