<?php

namespace Tests\Feature;

use App\Enums\RoasteryStatus;
use App\Enums\Role;
use App\Models\Roastery;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class SellerRoasteryBootstrapTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_seller_sees_only_scoped_roasteries_with_private_status_and_roles(): void
    {
        $seller = User::factory()->create();
        $owned = $this->roastery('owned-roastery', RoasteryStatus::Pending);
        $managed = $this->roastery('managed-roastery', RoasteryStatus::Verified);
        $foreign = $this->roastery('foreign-roastery', RoasteryStatus::Verified);

        UserRole::query()->create([
            'user_id' => $seller->id,
            'role' => Role::RoasteryOwner->value,
            'scope_type' => 'roastery',
            'scope_id' => $owned->id,
        ]);
        UserRole::query()->create([
            'user_id' => $seller->id,
            'role' => Role::RoasteryManager->value,
            'scope_type' => 'roastery',
            'scope_id' => $managed->id,
        ]);

        $this->authenticateWithRole($seller, Role::RoasteryOwner, 'roastery', $owned->id);
        UserRole::query()->firstOrCreate([
            'user_id' => $seller->id,
            'role' => Role::RoasteryManager->value,
            'scope_type' => 'roastery',
            'scope_id' => $managed->id,
        ]);

        $response = $this->getJson('/api/v1/seller/roasteries')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonFragment([
                'id' => $owned->id,
                'status' => 'pending',
                'access_roles' => ['roastery_owner'],
            ])
            ->assertJsonFragment([
                'id' => $managed->id,
                'status' => 'verified',
                'access_roles' => ['roastery_manager'],
            ]);

        $ids = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_administrator_can_bootstrap_all_roasteries(): void
    {
        $administrator = User::factory()->create();
        $first = $this->roastery('admin-first', RoasteryStatus::Pending);
        $second = $this->roastery('admin-second', RoasteryStatus::Suspended);
        $this->authenticateWithRole($administrator, Role::Administrator);

        $response = $this->getJson('/api/v1/seller/roasteries')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');

        $items = collect($response->json('data.items'))->keyBy('id');
        $this->assertSame(['administrator'], $items[$first->id]['access_roles']);
        $this->assertSame(['administrator'], $items[$second->id]['access_roles']);
    }

    private function roastery(string $slug, RoasteryStatus $status): Roastery
    {
        return Roastery::query()->create([
            'name' => 'روستری '.$slug,
            'slug' => $slug,
            'description' => '',
            'status' => $status->value,
            'verified_at' => $status === RoasteryStatus::Verified ? now() : null,
        ]);
    }
}
