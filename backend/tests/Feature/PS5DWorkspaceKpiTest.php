<?php

namespace Tests\Feature;

use App\Enums\RoasteryMembershipRole;
use App\Enums\RoasteryStatus;
use App\Enums\Role;
use App\Models\Roastery;
use App\Models\RoasteryMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class PS5DWorkspaceKpiTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_seller_workspace_is_scoped_and_returns_server_defined_non_financial_kpis(): void
    {
        $seller = User::factory()->create();
        $owned = $this->roastery('ps5d-owned', RoasteryStatus::Verified);
        $other = $this->roastery('ps5d-other', RoasteryStatus::Verified);

        RoasteryMembership::query()->create([
            'roastery_id' => $owned->id,
            'user_id' => $seller->id,
            'role' => RoasteryMembershipRole::Owner,
            'created_by' => $seller->id,
        ]);
        $this->authenticateWithRole($seller, Role::RoasteryOwner, 'roastery', $owned->id);

        $response = $this->getJson('/api/v1/seller/workspace')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.roastery.id', $owned->id)
            ->assertJsonPath('data.items.0.kpis.pending_acceptance', 0)
            ->assertJsonPath('data.items.0.kpis.active_fulfillment', 0)
            ->assertJsonPath('data.items.0.kpis.active_shipping', 0)
            ->assertJsonPath('data.items.0.kpis.open_incidents', 0)
            ->assertJsonMissing(['id' => $other->id]);

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString('gross_total', $content);
        $this->assertStringNotContainsString('net_total', $content);
        $this->assertStringNotContainsString('commission_total', $content);
    }

    public function test_admin_workspace_reports_authoritative_counts_and_is_role_guarded(): void
    {
        $this->roastery('ps5d-pending', RoasteryStatus::Pending);
        $this->roastery('ps5d-verified', RoasteryStatus::Verified);

        $customer = User::factory()->create();
        $this->authenticateWithRole($customer, Role::Customer);
        $this->getJson('/api/v1/admin/operations/workspace')->assertForbidden();

        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);
        $response = $this->getJson('/api/v1/admin/operations/workspace')
            ->assertOk()
            ->assertJsonPath('data.kpis.pending_roasteries', 1)
            ->assertJsonPath('data.kpis.products_in_review', 0)
            ->assertJsonPath('data.kpis.open_fulfillment_incidents', 0)
            ->assertJsonPath('data.kpis.failed_notifications', 0)
            ->assertJsonPath('data.kpis.open_financial_reconciliation', 0);

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString('"revenue"', $content);
        $this->assertStringNotContainsString('"gmv"', $content);
    }

    private function roastery(string $slug, RoasteryStatus $status): Roastery
    {
        return Roastery::query()->create([
            'name' => 'روستری '.str_replace('-', ' ', $slug),
            'slug' => $slug,
            'status' => $status,
            'verified_at' => $status === RoasteryStatus::Verified ? now() : null,
        ]);
    }
}
