<?php

namespace Tests\Feature;

use App\Enums\RoasteryMembershipRole;
use App\Enums\Role;
use App\Models\Roastery;
use App\Models\RoasteryMembership;
use App\Models\User;
use App\Services\Seller\SellerAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class SellerOrganizationSecurityTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_specialized_roles_are_server_side_least_privilege(): void
    {
        $roastery = $this->roastery('primary');

        $catalog = $this->member($roastery, RoasteryMembershipRole::Catalog);
        $this->authenticateWithRole($catalog, Role::Customer);
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/products")->assertOk();
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/orders")->assertForbidden();
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/settlements")->assertForbidden();

        $fulfillment = $this->member($roastery, RoasteryMembershipRole::Fulfillment);
        $this->authenticateWithRole($fulfillment, Role::Customer);
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/orders")->assertOk();
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/products")->assertForbidden();
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/settlements")->assertForbidden();

        $finance = $this->member($roastery, RoasteryMembershipRole::Finance);
        $this->authenticateWithRole($finance, Role::Customer);
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/settlements")->assertOk();
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/orders")->assertForbidden();
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/products")->assertForbidden();

        $support = $this->member($roastery, RoasteryMembershipRole::Support);
        $this->authenticateWithRole($support, Role::Customer);
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/orders")->assertOk();
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/products")->assertForbidden();
        $this->postJson("/api/v1/seller/roasteries/{$roastery->id}/closures", [
            'starts_at' => now()->addHour()->toIso8601String(),
            'ends_at' => now()->addHours(2)->toIso8601String(),
            'blocks_new_orders' => true,
        ])->assertForbidden();
    }

    public function test_owner_and_manager_organization_boundaries_are_explicit(): void
    {
        $roastery = $this->roastery('org');
        $owner = $this->member($roastery, RoasteryMembershipRole::Owner);
        $manager = $this->member($roastery, RoasteryMembershipRole::Manager);

        $this->authenticateWithRole($owner, Role::Customer);
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/organization")
            ->assertOk()
            ->assertJsonPath('data.role', 'owner');
        $this->postJson("/api/v1/seller/roasteries/{$roastery->id}/invitations", [
            'mobile' => '09120000111',
            'role' => 'manager',
        ])->assertCreated();

        $this->authenticateWithRole($manager, Role::Customer);
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/organization")
            ->assertOk()
            ->assertJsonPath('data.role', 'manager');
        $this->postJson("/api/v1/seller/roasteries/{$roastery->id}/invitations", [
            'mobile' => '09120000112',
            'role' => 'owner',
        ])->assertForbidden();
        $this->postJson("/api/v1/seller/roasteries/{$roastery->id}/invitations", [
            'mobile' => '09120000113',
            'role' => 'catalog',
        ])->assertCreated();
    }

    public function test_cross_roastery_identifier_is_hidden_even_with_a_valid_seller_alias(): void
    {
        $owned = $this->roastery('owned');
        $foreign = $this->roastery('foreign');
        $catalog = $this->member($owned, RoasteryMembershipRole::Catalog);
        $this->authenticateWithRole($catalog, Role::Customer);

        $this->getJson("/api/v1/seller/roasteries/{$foreign->id}/products")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'request.not_found');
        $this->getJson("/api/v1/seller/roasteries/{$foreign->id}/organization")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'request.not_found');
    }

    public function test_administrator_has_read_revoke_lock_oversight_but_no_hidden_seller_write(): void
    {
        $roastery = $this->roastery('admin');
        $owner = $this->member($roastery, RoasteryMembershipRole::Owner);
        $secondOwner = $this->member($roastery, RoasteryMembershipRole::Owner);
        $admin = User::factory()->create();
        $this->authenticateWithRole($admin, Role::Administrator);

        $this->getJson("/api/v1/admin/seller-organizations/roasteries/{$roastery->id}")
            ->assertOk()
            ->assertJsonPath('data.impersonation', false);
        $this->getJson("/api/v1/seller/roasteries/{$roastery->id}")->assertOk();
        $this->patchJson("/api/v1/seller/roasteries/{$roastery->id}", [
            'name' => 'نام غیرمجاز',
        ])->assertForbidden();

        $this->patchJson("/api/v1/admin/seller-organizations/memberships/{$secondOwner->id}/lock", [
            'locked' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_locked', true);

        $this->patchJson("/api/v1/admin/seller-organizations/memberships/{$owner->id}/lock", [
            'locked' => true,
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'seller.last_owner_required');
    }

    private function roastery(string $suffix): Roastery
    {
        return Roastery::query()->create([
            'name' => 'روستری '.$suffix,
            'slug' => 'seller-org-'.$suffix,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
            'timezone' => 'Asia/Tehran',
        ]);
    }

    private function member(Roastery $roastery, RoasteryMembershipRole $role): User
    {
        $user = User::factory()->create();
        $membership = RoasteryMembership::query()->create([
            'roastery_id' => $roastery->id,
            'user_id' => $user->id,
            'role' => $role,
            'created_by' => $user->id,
        ]);
        app(SellerAccess::class)->syncLegacyRole($membership);

        return $user;
    }
}
