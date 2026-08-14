<?php

namespace Tests\Unit;

use App\Enums\RoasteryMembershipRole;
use App\Enums\SellerPermission;
use App\Services\Seller\SellerAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SellerPermissionMatrixTest extends TestCase
{
    /** @return array<string, array{string, SellerPermission}> */
    public static function routes(): array
    {
        return [
            'roastery_show' => ['api.v1.seller.roasteries.show', SellerPermission::WorkspaceRead],
            'roastery_update' => ['api.v1.seller.roasteries.update', SellerPermission::OrganizationManage],
            'media_index' => ['api.v1.seller.media.index', SellerPermission::CatalogRead],
            'media_store' => ['api.v1.seller.media.store', SellerPermission::CatalogWrite],
            'media_upload_show' => ['api.v1.seller.media.uploads.show', SellerPermission::CatalogRead],
            'media_upload_create' => ['api.v1.seller.media.uploads.create', SellerPermission::CatalogWrite],
            'media_upload_complete' => ['api.v1.seller.media.uploads.complete', SellerPermission::CatalogWrite],
            'media_upload_retry' => ['api.v1.seller.media.uploads.retry', SellerPermission::CatalogWrite],
            'products_index' => ['api.v1.seller.products.index', SellerPermission::CatalogRead],
            'products_show' => ['api.v1.seller.products.show', SellerPermission::CatalogRead],
            'products_store' => ['api.v1.seller.products.store', SellerPermission::CatalogWrite],
            'products_update' => ['api.v1.seller.products.update', SellerPermission::CatalogWrite],
            'variants_store' => ['api.v1.seller.variants.store', SellerPermission::CatalogWrite],
            'variants_update' => ['api.v1.seller.variants.update', SellerPermission::CatalogWrite],
            'batches_index' => ['api.v1.seller.roast_batches.index', SellerPermission::CatalogRead],
            'batches_store' => ['api.v1.seller.roast_batches.store', SellerPermission::CatalogWrite],
            'grinding_show' => ['api.v1.seller.grinding_capability.show', SellerPermission::CatalogRead],
            'grinding_update' => ['api.v1.seller.grinding_capability.update', SellerPermission::CatalogWrite],
            'inventory_index' => ['api.v1.seller.inventory.index', SellerPermission::InventoryRead],
            'inventory_adjust' => ['api.v1.seller.inventory.adjust', SellerPermission::InventoryWrite],
            'orders_index' => ['api.v1.seller.orders.index', SellerPermission::OrdersRead],
            'orders_show' => ['api.v1.seller.orders.show', SellerPermission::OrdersRead],
            'fulfillment_write' => ['api.v1.seller.orders.fulfillment', SellerPermission::FulfillmentWrite],
            'incident_write' => ['api.v1.seller.orders.incidents.store', SellerPermission::FulfillmentWrite],
            'settlements_index' => ['api.v1.seller.settlements.index', SellerPermission::FinanceRead],
            'organization_show' => ['api.v1.seller.organization.show', SellerPermission::OrganizationRead],
            'members_index' => ['api.v1.seller.members.index', SellerPermission::OrganizationRead],
            'members_update' => ['api.v1.seller.members.update', SellerPermission::OrganizationManage],
            'members_destroy' => ['api.v1.seller.members.destroy', SellerPermission::OrganizationManage],
            'invites_index' => ['api.v1.seller.invitations.index', SellerPermission::OrganizationRead],
            'invites_store' => ['api.v1.seller.invitations.store', SellerPermission::OrganizationManage],
            'invites_destroy' => ['api.v1.seller.invitations.destroy', SellerPermission::OrganizationManage],
            'schedule_show' => ['api.v1.seller.schedule.show', SellerPermission::AvailabilityRead],
            'schedule_update' => ['api.v1.seller.schedule.update', SellerPermission::AvailabilityWrite],
            'closures_index' => ['api.v1.seller.closures.index', SellerPermission::AvailabilityRead],
            'closures_store' => ['api.v1.seller.closures.store', SellerPermission::AvailabilityWrite],
            'closures_destroy' => ['api.v1.seller.closures.destroy', SellerPermission::AvailabilityWrite],
            'promotions_index' => ['api.v1.seller.promotions.index', SellerPermission::PromotionRead],
            'promotions_store' => ['api.v1.seller.promotions.store', SellerPermission::PromotionWrite],
            'promotions_update' => ['api.v1.seller.promotions.update', SellerPermission::PromotionWrite],
        ];
    }

    #[DataProvider('routes')]
    public function test_every_seller_route_has_an_explicit_permission(
        string $routeName,
        SellerPermission $permission,
    ): void {
        $access = new SellerAccess;

        self::assertSame($permission, $access->permissionForRoute($routeName));

        foreach (RoasteryMembershipRole::cases() as $role) {
            $actual = in_array($permission, $access->permissionsForRole($role), true);
            self::assertSame(
                $this->expected($role, $permission),
                $actual,
                $role->value.' permission mismatch for '.$routeName,
            );
        }
    }

    public function test_unknown_scoped_seller_route_is_fail_closed(): void
    {
        self::assertNull((new SellerAccess)->permissionForRoute('api.v1.seller.unreviewed.write'));
    }

    private function expected(RoasteryMembershipRole $role, SellerPermission $permission): bool
    {
        return match ($role) {
            RoasteryMembershipRole::Owner => true,
            RoasteryMembershipRole::Manager => ! in_array($permission, [], true),
            RoasteryMembershipRole::Catalog => in_array($permission, [
                SellerPermission::WorkspaceRead,
                SellerPermission::AvailabilityRead,
                SellerPermission::CatalogRead,
                SellerPermission::CatalogWrite,
                SellerPermission::InventoryRead,
                SellerPermission::InventoryWrite,
            ], true),
            RoasteryMembershipRole::Fulfillment => in_array($permission, [
                SellerPermission::WorkspaceRead,
                SellerPermission::AvailabilityRead,
                SellerPermission::InventoryRead,
                SellerPermission::InventoryWrite,
                SellerPermission::OrdersRead,
                SellerPermission::FulfillmentWrite,
            ], true),
            RoasteryMembershipRole::Finance => in_array($permission, [
                SellerPermission::WorkspaceRead,
                SellerPermission::AvailabilityRead,
                SellerPermission::FinanceRead,
            ], true),
            RoasteryMembershipRole::Support => in_array($permission, [
                SellerPermission::WorkspaceRead,
                SellerPermission::AvailabilityRead,
                SellerPermission::OrdersRead,
            ], true),
        };
    }
}
