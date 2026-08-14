<?php

namespace Tests\Feature;

use App\Enums\RoasteryMembershipRole;
use App\Enums\Role;
use App\Models\Roastery;
use App\Models\RoasteryInvitation;
use App\Models\RoasteryMembership;
use App\Models\User;
use App\Services\Seller\SellerAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class SellerInvitationLifecycleTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_invite_token_is_hashed_account_bound_single_use_and_outbox_safe_at_rest(): void
    {
        [$roastery, $owner] = $this->ownerFixture('invite');
        $target = User::factory()->create(['mobile' => '09120000221']);
        $wrong = User::factory()->create(['mobile' => '09120000222']);
        $this->authenticateWithRole($owner, Role::Customer);

        $payload = $this->postJson("/api/v1/seller/roasteries/{$roastery->id}/invitations", [
            'mobile' => $target->mobile,
            'role' => 'fulfillment',
        ])
            ->assertCreated()
            ->assertJsonPath('data.invitation.status', 'pending')
            ->json('data');
        $token = (string) $payload['token'];
        $inviteId = (string) $payload['invitation']['id'];

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertDatabaseHas('roastery_invitations', [
            'id' => $inviteId,
            'token_hash' => hash('sha256', $token),
            'target_mobile_hash' => hash('sha256', '09120000221'),
        ]);
        $rawInvite = DB::table('roastery_invitations')->where('id', $inviteId)->first();
        $this->assertNotSame('09120000221', $rawInvite?->target_mobile);
        $rawOutbox = (string) DB::table('notification_outbox')
            ->where('deduplication_key', 'seller-invite:'.$inviteId)
            ->value('payload');
        $this->assertStringNotContainsString($token, $rawOutbox);

        $this->authenticateWithRole($wrong, Role::Customer);
        $this->postJson('/api/v1/seller/invitations/accept', ['token' => $token])
            ->assertNotFound();
        $this->assertDatabaseMissing('roastery_memberships', [
            'roastery_id' => $roastery->id,
            'user_id' => $wrong->id,
        ]);

        $this->authenticateWithRole($target, Role::Customer);
        $this->postJson('/api/v1/seller/invitations/accept', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.membership.role', 'fulfillment');
        $this->assertDatabaseCount('roastery_memberships', 2);

        $this->postJson('/api/v1/seller/invitations/accept', ['token' => $token])
            ->assertConflict()
            ->assertJsonPath('error.code', 'seller.invite_unavailable');
        $this->assertDatabaseCount('roastery_memberships', 2);
    }

    public function test_expired_and_revoked_invites_cannot_be_replayed(): void
    {
        [$roastery, $owner] = $this->ownerFixture('expiry');
        $expiredTarget = User::factory()->create(['mobile' => '09120000231']);
        $revokedTarget = User::factory()->create(['mobile' => '09120000232']);
        $this->authenticateWithRole($owner, Role::Customer);

        $expired = $this->postJson("/api/v1/seller/roasteries/{$roastery->id}/invitations", [
            'mobile' => $expiredTarget->mobile,
            'role' => 'catalog',
        ])->assertCreated()->json('data');
        RoasteryInvitation::query()
            ->whereKey($expired['invitation']['id'])
            ->update(['expires_at' => now()->subSecond()]);

        $this->authenticateWithRole($expiredTarget, Role::Customer);
        $this->postJson('/api/v1/seller/invitations/accept', ['token' => $expired['token']])
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'seller.invite_expired');

        $this->authenticateWithRole($owner, Role::Customer);
        $revoked = $this->postJson("/api/v1/seller/roasteries/{$roastery->id}/invitations", [
            'mobile' => $revokedTarget->mobile,
            'role' => 'support',
        ])->assertCreated()->json('data');
        $this->deleteJson(
            "/api/v1/seller/roasteries/{$roastery->id}/invitations/{$revoked['invitation']['id']}",
        )->assertOk();

        $this->authenticateWithRole($revokedTarget, Role::Customer);
        $this->postJson('/api/v1/seller/invitations/accept', ['token' => $revoked['token']])
            ->assertConflict()
            ->assertJsonPath('error.code', 'seller.invite_unavailable');
    }

    public function test_last_owner_cannot_be_removed_or_demoted_and_second_owner_unlocks_controlled_change(): void
    {
        [$roastery, $owner, $membership] = $this->ownerFixture('owner', includeMembership: true);
        $this->authenticateWithRole($owner, Role::Customer);

        $this->patchJson("/api/v1/seller/roasteries/{$roastery->id}/members/{$membership->id}", [
            'role' => 'manager',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'seller.last_owner_required');
        $this->deleteJson("/api/v1/seller/roasteries/{$roastery->id}/members/{$membership->id}")
            ->assertConflict()
            ->assertJsonPath('error.code', 'seller.last_owner_required');

        $second = User::factory()->create();
        $secondMembership = RoasteryMembership::query()->create([
            'roastery_id' => $roastery->id,
            'user_id' => $second->id,
            'role' => RoasteryMembershipRole::Owner,
            'created_by' => $owner->id,
        ]);
        app(SellerAccess::class)->syncLegacyRole($secondMembership);

        $this->patchJson("/api/v1/seller/roasteries/{$roastery->id}/members/{$membership->id}", [
            'role' => 'manager',
        ])
            ->assertOk()
            ->assertJsonPath('data.membership.role', 'manager');
        $this->assertDatabaseHas('roastery_memberships', [
            'id' => $membership->id,
            'role' => 'manager',
        ]);
    }

    /** @return array{Roastery, User}|array{Roastery, User, RoasteryMembership} */
    private function ownerFixture(string $suffix, bool $includeMembership = false): array
    {
        $roastery = Roastery::query()->create([
            'name' => 'روستری '.$suffix,
            'slug' => 'seller-invite-'.$suffix,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
            'timezone' => 'Asia/Tehran',
        ]);
        $owner = User::factory()->create();
        $membership = RoasteryMembership::query()->create([
            'roastery_id' => $roastery->id,
            'user_id' => $owner->id,
            'role' => RoasteryMembershipRole::Owner,
            'created_by' => $owner->id,
        ]);
        app(SellerAccess::class)->syncLegacyRole($membership);

        return $includeMembership
            ? [$roastery, $owner, $membership]
            : [$roastery, $owner];
    }
}
