<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuthSession;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Identity\AuthSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IdentityAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_an_active_owned_session_and_exposes_stable_roles(): void
    {
        $user = User::factory()->create();
        $this->authenticate($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.mobile', $user->mobile)
            ->assertJsonPath('data.roles.0', 'customer');
    }

    public function test_expired_recorded_session_is_rejected_even_when_cookie_auth_exists(): void
    {
        $user = User::factory()->create();
        $this->authenticate($user, expiresAt: now()->subMinute());

        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'request.unauthenticated');

        $this->assertDatabaseHas('auth_sessions', [
            'user_id' => $user->id,
            'revoke_reason' => 'inactive_request',
        ]);
    }

    public function test_logout_revokes_the_current_recorded_session(): void
    {
        $user = User::factory()->create();
        $session = $this->authenticate($user);

        $this->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertNotNull($session->fresh()?->revoked_at);
        $this->assertSame('logout', $session->fresh()?->revoke_reason);
    }

    public function test_address_defaults_and_owner_scope_are_enforced(): void
    {
        $owner = User::factory()->create();
        $this->authenticate($owner);

        $first = $this->postJson('/api/v1/me/addresses', $this->addressPayload([
            'title' => 'خانه',
            'is_default' => false,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->json('data');

        $second = $this->postJson('/api/v1/me/addresses', $this->addressPayload([
            'title' => 'محل کار',
            'address_line' => 'خیابان دوم، پلاک ۲',
            'is_default' => true,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->json('data');

        $this->assertDatabaseHas('addresses', [
            'id' => $first['id'],
            'user_id' => $owner->id,
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('addresses', [
            'id' => $second['id'],
            'user_id' => $owner->id,
            'is_default' => true,
        ]);

        $otherUser = User::factory()->create();
        $foreign = Address::query()->create([
            'user_id' => $otherUser->id,
            ...$this->addressPayload(['title' => 'آدرس خصوصی']),
        ]);

        $this->patchJson(
            '/api/v1/me/addresses/'.$foreign->id,
            $this->addressPayload(['title' => 'تلاش برای تغییر']),
        )->assertNotFound()->assertJsonPath('error.code', 'request.not_found');

        $this->deleteJson('/api/v1/me/addresses/'.$foreign->id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'request.not_found');

        $this->assertDatabaseHas('addresses', [
            'id' => $foreign->id,
            'user_id' => $otherUser->id,
            'title' => 'آدرس خصوصی',
        ]);

        $this->deleteJson('/api/v1/me/addresses/'.$second['id'])
            ->assertNoContent();

        $this->assertDatabaseHas('addresses', [
            'id' => $first['id'],
            'user_id' => $owner->id,
            'is_default' => true,
        ]);
    }

    private function authenticate(
        User $user,
        ?\DateTimeInterface $expiresAt = null,
    ): AuthSession {
        UserRole::query()->firstOrCreate([
            'user_id' => $user->id,
            'role' => 'customer',
            'scope_type' => 'global',
            'scope_id' => 'global',
        ]);

        $session = AuthSession::query()->create([
            'user_id' => $user->id,
            'last_seen_at' => now(),
            'expires_at' => $expiresAt ?? now()->addHour(),
        ]);

        $this->actingAs($user, 'web')->withSession([
            AuthSessionService::SESSION_KEY => $session->id,
        ]);

        return $session;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function addressPayload(array $overrides = []): array
    {
        return [
            'title' => 'خانه',
            'recipient_name' => 'سجاد خواص',
            'recipient_mobile' => '09123456789',
            'province' => 'البرز',
            'city' => 'کرج',
            'address_line' => 'خیابان اول، پلاک ۱',
            'postal_code' => '1234567890',
            'is_default' => false,
            ...$overrides,
        ];
    }
}
