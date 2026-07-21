<?php

namespace App\Services\Identity;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserRole;

final class RoleService
{
    public function ensureCustomer(User $user): UserRole
    {
        return $this->assign($user, Role::Customer);
    }

    public function assign(
        User $user,
        Role $role,
        string $scopeType = 'global',
        string $scopeId = 'global',
    ): UserRole {
        return UserRole::query()->firstOrCreate([
            'user_id' => $user->id,
            'role' => $role->value,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);
    }
}
