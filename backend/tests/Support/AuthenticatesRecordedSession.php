<?php

namespace Tests\Support;

use App\Enums\Role;
use App\Models\AuthSession;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Identity\AuthSessionService;

trait AuthenticatesRecordedSession
{
    protected function authenticateWithRole(
        User $user,
        Role $role = Role::Customer,
        string $scopeType = 'global',
        string $scopeId = 'global',
    ): AuthSession {
        UserRole::query()->firstOrCreate([
            'user_id' => $user->id,
            'role' => $role->value,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);

        $session = AuthSession::query()->create([
            'user_id' => $user->id,
            'last_seen_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user, 'web')->withSession([
            AuthSessionService::SESSION_KEY => $session->id,
        ]);

        return $session;
    }
}
