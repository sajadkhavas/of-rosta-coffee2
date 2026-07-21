<?php

namespace App\Services\Identity;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserRole;

final class RoleService
{
    public function ensureCustomer(User $user): UserRole
    {
        return UserRole::query()->firstOrCreate([
            'user_id' => $user->id,
            'role' => Role::Customer->value,
            'scope_type' => 'global',
            'scope_id' => 'global',
        ]);
    }
}
