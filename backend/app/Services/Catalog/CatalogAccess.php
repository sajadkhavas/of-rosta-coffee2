<?php

namespace App\Services\Catalog;

use App\Enums\Role;
use App\Models\Roastery;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class CatalogAccess
{
    /**
     * @param  list<Role>  $allowedRoles
     */
    public function assertRoasteryAccess(
        User $user,
        Roastery $roastery,
        array $allowedRoles = [
            Role::RoasteryOwner,
            Role::RoasteryManager,
            Role::RoasteryStaff,
        ],
    ): void {
        if ($user->hasRole(Role::Administrator)) {
            return;
        }

        $roleValues = array_map(
            static fn (Role $role): string => $role->value,
            $allowedRoles,
        );

        $allowed = $user->roleAssignments()
            ->whereIn('role', $roleValues)
            ->where('scope_type', 'roastery')
            ->where('scope_id', $roastery->id)
            ->exists();

        if (! $allowed) {
            throw (new ModelNotFoundException)->setModel(
                Roastery::class,
                [$roastery->id],
            );
        }
    }

    public function assertAdministrator(User $user): void
    {
        if (! $user->hasRole(Role::Administrator)) {
            throw new AuthorizationException;
        }
    }
}
