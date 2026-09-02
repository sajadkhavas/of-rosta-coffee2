<?php

namespace App\Services\Seller;

use App\Enums\RoasteryMembershipRole;
use App\Enums\Role;
use App\Enums\SellerPermission;
use App\Models\Roastery;
use App\Models\RoasteryMembership;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class SellerAccess
{
    /** @return list<SellerPermission> */
    public function permissionsForRole(RoasteryMembershipRole $role): array
    {
        $read = [SellerPermission::WorkspaceRead, SellerPermission::AvailabilityRead];

        return match ($role) {
            RoasteryMembershipRole::Owner => SellerPermission::cases(),
            RoasteryMembershipRole::Manager => [
                ...$read,
                SellerPermission::OrganizationRead,
                SellerPermission::OrganizationManage,
                SellerPermission::CatalogRead,
                SellerPermission::CatalogWrite,
                SellerPermission::InventoryRead,
                SellerPermission::InventoryWrite,
                SellerPermission::OrdersRead,
                SellerPermission::FulfillmentWrite,
                SellerPermission::FinanceRead,
                SellerPermission::AvailabilityWrite,
                SellerPermission::PromotionRead,
                SellerPermission::PromotionWrite,
            ],
            RoasteryMembershipRole::Catalog => [
                ...$read,
                SellerPermission::CatalogRead,
                SellerPermission::CatalogWrite,
                SellerPermission::InventoryRead,
                SellerPermission::InventoryWrite,
            ],
            RoasteryMembershipRole::Fulfillment => [
                ...$read,
                SellerPermission::InventoryRead,
                SellerPermission::InventoryWrite,
                SellerPermission::OrdersRead,
                SellerPermission::FulfillmentWrite,
            ],
            RoasteryMembershipRole::Finance => [
                ...$read,
                SellerPermission::FinanceRead,
            ],
            RoasteryMembershipRole::Support => [
                ...$read,
                SellerPermission::OrdersRead,
            ],
        };
    }

    public function effectiveRole(User $user, string $roasteryId): ?RoasteryMembershipRole
    {
        $membership = RoasteryMembership::query()
            ->where('roastery_id', $roasteryId)
            ->where('user_id', $user->id)
            ->first();

        if ($membership instanceof RoasteryMembership) {
            return $membership->is_locked ? null : $membership->role;
        }

        $legacyRoles = UserRole::query()
            ->where('user_id', $user->id)
            ->where('scope_type', 'roastery')
            ->where('scope_id', $roasteryId)
            ->pluck('role')
            ->map(static fn (Role|string $role): string => $role instanceof Role ? $role->value : $role)
            ->all();

        if (in_array(Role::RoasteryOwner->value, $legacyRoles, true)) {
            return RoasteryMembershipRole::Owner;
        }
        if (in_array(Role::RoasteryManager->value, $legacyRoles, true)) {
            return RoasteryMembershipRole::Manager;
        }
        if (in_array(Role::RoasteryStaff->value, $legacyRoles, true)) {
            return RoasteryMembershipRole::Catalog;
        }

        return null;
    }

    /** @return array<string, list<string>> */
    public function rolesByRoastery(User $user): array
    {
        $result = [];
        foreach (RoasteryMembership::query()
            ->where('user_id', $user->id)
            ->where('is_locked', false)
            ->get() as $membership) {
            $result[$membership->roastery_id] = [match ($membership->role) {
                RoasteryMembershipRole::Owner => Role::RoasteryOwner->value,
                RoasteryMembershipRole::Manager => Role::RoasteryManager->value,
                default => Role::RoasteryStaff->value,
            }];
        }

        foreach (UserRole::query()
            ->where('user_id', $user->id)
            ->where('scope_type', 'roastery')
            ->whereIn('role', [
                Role::RoasteryOwner->value,
                Role::RoasteryManager->value,
                Role::RoasteryStaff->value,
            ])
            ->get() as $assignment) {
            if (! isset($result[$assignment->scope_id])) {
                $result[$assignment->scope_id] ??= [];
                $value = $assignment->role->value;
                if (! in_array($value, $result[$assignment->scope_id], true)) {
                    $result[$assignment->scope_id][] = $value;
                }
            }
        }

        foreach ($result as &$roles) {
            sort($roles);
        }
        unset($roles);

        return $result;
    }

    public function assertPermission(
        User $user,
        Roastery $roastery,
        SellerPermission $permission,
    ): void {
        if ($user->hasRole(Role::Administrator)) {
            if ($this->isReadPermission($permission)) {
                return;
            }
            throw new AuthorizationException;
        }

        $role = $this->effectiveRole($user, $roastery->id);
        if ($role === null) {
            throw (new ModelNotFoundException)->setModel(Roastery::class, [$roastery->id]);
        }

        if (! in_array($permission, $this->permissionsForRole($role), true)) {
            throw new AuthorizationException;
        }
    }

    public function permissionForRoute(string $routeName): ?SellerPermission
    {
        return match ($routeName) {
            'api.v1.seller.roasteries.show' => SellerPermission::WorkspaceRead,
            'api.v1.seller.roasteries.update' => SellerPermission::OrganizationManage,
            'api.v1.seller.media.index',
            'api.v1.seller.media.uploads.show' => SellerPermission::CatalogRead,
            'api.v1.seller.media.store',
            'api.v1.seller.media.uploads.create',
            'api.v1.seller.media.uploads.complete',
            'api.v1.seller.media.uploads.retry' => SellerPermission::CatalogWrite,
            'api.v1.seller.products.index',
            'api.v1.seller.products.show',
            'api.v1.seller.roast_batches.index',
            'api.v1.seller.grinding_capability.show' => SellerPermission::CatalogRead,
            'api.v1.seller.products.store',
            'api.v1.seller.products.update',
            'api.v1.seller.variants.store',
            'api.v1.seller.variants.update',
            'api.v1.seller.roast_batches.store',
            'api.v1.seller.grinding_capability.update' => SellerPermission::CatalogWrite,
            'api.v1.seller.inventory.index' => SellerPermission::InventoryRead,
            'api.v1.seller.inventory.adjust' => SellerPermission::InventoryWrite,
            'api.v1.seller.orders.index',
            'api.v1.seller.orders.show' => SellerPermission::OrdersRead,
            'api.v1.seller.orders.fulfillment',
            'api.v1.seller.orders.incidents.store' => SellerPermission::FulfillmentWrite,
            'api.v1.seller.settlements.index',
            'api.v1.seller.settlement_profile.show' => SellerPermission::FinanceRead,
            'api.v1.seller.settlement_profile.update' => SellerPermission::OrganizationManage,
            'api.v1.seller.organization.show',
            'api.v1.seller.members.index',
            'api.v1.seller.invitations.index' => SellerPermission::OrganizationRead,
            'api.v1.seller.members.update',
            'api.v1.seller.members.destroy',
            'api.v1.seller.invitations.store',
            'api.v1.seller.invitations.destroy' => SellerPermission::OrganizationManage,
            'api.v1.seller.schedule.show',
            'api.v1.seller.closures.index' => SellerPermission::AvailabilityRead,
            'api.v1.seller.schedule.update',
            'api.v1.seller.closures.store',
            'api.v1.seller.closures.destroy' => SellerPermission::AvailabilityWrite,
            'api.v1.seller.promotions.index' => SellerPermission::PromotionRead,
            'api.v1.seller.promotions.store',
            'api.v1.seller.promotions.update' => SellerPermission::PromotionWrite,
            default => null,
        };
    }

    public function syncLegacyRole(RoasteryMembership $membership): void
    {
        UserRole::query()
            ->where('user_id', $membership->user_id)
            ->where('scope_type', 'roastery')
            ->where('scope_id', $membership->roastery_id)
            ->whereIn('role', [
                Role::RoasteryOwner->value,
                Role::RoasteryManager->value,
                Role::RoasteryStaff->value,
            ])
            ->delete();

        if ($membership->is_locked) {
            return;
        }

        UserRole::query()->create([
            'user_id' => $membership->user_id,
            'role' => match ($membership->role) {
                RoasteryMembershipRole::Owner => Role::RoasteryOwner->value,
                RoasteryMembershipRole::Manager => Role::RoasteryManager->value,
                default => Role::RoasteryStaff->value,
            },
            'scope_type' => 'roastery',
            'scope_id' => $membership->roastery_id,
        ]);
    }

    public function removeLegacyRole(RoasteryMembership $membership): void
    {
        UserRole::query()
            ->where('user_id', $membership->user_id)
            ->where('scope_type', 'roastery')
            ->where('scope_id', $membership->roastery_id)
            ->whereIn('role', [
                Role::RoasteryOwner->value,
                Role::RoasteryManager->value,
                Role::RoasteryStaff->value,
            ])
            ->delete();
    }

    private function isReadPermission(SellerPermission $permission): bool
    {
        return in_array($permission, [
            SellerPermission::WorkspaceRead,
            SellerPermission::OrganizationRead,
            SellerPermission::CatalogRead,
            SellerPermission::InventoryRead,
            SellerPermission::OrdersRead,
            SellerPermission::FinanceRead,
            SellerPermission::AvailabilityRead,
            SellerPermission::PromotionRead,
        ], true);
    }
}
