<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\GrindingProfile;
use App\Models\Roastery;
use App\Models\RoasteryGrindingCapability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class R5EGrindingCapabilityTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_owner_can_publish_a_scoped_free_grinding_capability(): void
    {
        $owner = User::factory()->create();
        $owned = $this->roastery('r5e-owned');
        $foreign = $this->roastery('r5e-foreign');
        $this->authenticateWithRole($owner, Role::RoasteryOwner, 'roastery', $owned->id);

        $profiles = GrindingProfile::query()->where('is_active', true)->orderBy('code')->get();
        $this->assertCount(9, $profiles);

        $this->getJson('/api/v1/grinding-profiles')
            ->assertOk()
            ->assertJsonCount(9, 'data');

        $payload = [
            'availability' => 'available',
            'fee_mode' => 'free',
            'fee_amount' => 999_999,
            'preparation_minutes' => 30,
            'capacity_per_day' => 80,
            'supported_weights' => [500, 250, 250],
            'grinding_profile_ids' => $profiles->take(3)->pluck('id')->all(),
            'is_active' => true,
        ];

        $this->patchJson(
            '/api/v1/seller/roasteries/'.$foreign->id.'/grinding-capability',
            $payload,
        )->assertNotFound();

        $this->patchJson(
            '/api/v1/seller/roasteries/'.$owned->id.'/grinding-capability',
            $payload,
        )
            ->assertSuccessful()
            ->assertJsonPath('data.availability', 'available')
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.fee_mode', 'free')
            ->assertJsonPath('data.fee_amount', 0)
            ->assertJsonPath('data.is_free', true)
            ->assertJsonPath('data.supported_weights', [250, 500])
            ->assertJsonCount(3, 'data.profiles');

        $this->getJson('/api/v1/roasteries/'.$owned->slug.'/grinding-capability')
            ->assertOk()
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.label', 'آسیاب روستری رایگان')
            ->assertJsonCount(3, 'data.profiles');

        $capability = RoasteryGrindingCapability::query()
            ->where('roastery_id', $owned->id)
            ->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog.roastery_grinding_capability.updated',
            'auditable_id' => $capability->id,
        ]);
    }

    public function test_fixed_grinding_requires_positive_money_and_available_requires_profiles(): void
    {
        $owner = User::factory()->create();
        $roastery = $this->roastery('r5e-validation');
        $this->authenticateWithRole($owner, Role::RoasteryOwner, 'roastery', $roastery->id);

        $base = [
            'availability' => 'available',
            'fee_mode' => 'fixed',
            'fee_amount' => 0,
            'preparation_minutes' => 15,
            'capacity_per_day' => null,
            'supported_weights' => [250],
            'grinding_profile_ids' => [],
            'is_active' => true,
        ];

        $this->patchJson(
            '/api/v1/seller/roasteries/'.$roastery->id.'/grinding-capability',
            $base,
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'request.validation_failed');

        $profile = GrindingProfile::query()->where('is_active', true)->firstOrFail();
        $this->patchJson(
            '/api/v1/seller/roasteries/'.$roastery->id.'/grinding-capability',
            [
                ...$base,
                'fee_amount' => 100_000,
                'grinding_profile_ids' => [$profile->id],
            ],
        )
            ->assertSuccessful()
            ->assertJsonPath('data.fee_amount', 100_000)
            ->assertJsonPath('data.is_free', false);
    }

    private function roastery(string $slug): Roastery
    {
        return Roastery::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'city' => 'تهران',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }
}
