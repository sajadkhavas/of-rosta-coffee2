<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Roastery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class PS10SettlementProfileGateTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_administrator_cannot_create_settlement_batch_without_verified_destination_profile(): void
    {
        $administrator = User::factory()->create();
        $roastery = Roastery::query()->create([
            'name' => 'روستری بدون مقصد تسویه',
            'slug' => 'ps10-unverified-settlement-destination',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->authenticateWithRole($administrator, Role::Administrator);

        $this->postJson('/api/v1/admin/finance/settlement-batches', [
            'roastery_id' => $roastery->id,
            'currency' => 'IRR',
            'idempotency_key' => 'ps10-profile-gate-001',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'settlement.profile_not_verified');

        $this->assertDatabaseCount('settlement_batches', 0);
    }
}
