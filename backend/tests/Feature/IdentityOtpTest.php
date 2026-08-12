<?php

namespace Tests\Feature;

use App\Contracts\OtpSender;
use App\Models\AuthSession;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeOtpSender;
use Tests\TestCase;

final class IdentityOtpTest extends TestCase
{
    use RefreshDatabase;

    private FakeOtpSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = new FakeOtpSender;
        $this->app->instance(OtpSender::class, $this->sender);
    }

    public function test_otp_request_and_verification_create_a_bounded_customer_session(): void
    {
        $request = $this->postJson('/api/v1/auth/otp/request', [
            'mobile' => '۰۹۱۲۳۴۵۶۷۸۹',
            'purpose' => 'register',
        ]);

        $request
            ->assertAccepted()
            ->assertJsonStructure([
                'data' => ['request_id', 'expires_in', 'retry_after'],
            ])
            ->assertJsonPath('data.expires_in', 120)
            ->assertJsonPath('data.retry_after', 60);

        $message = $this->sender->latest();
        $challengeId = (string) $request->json('data.request_id');
        $challenge = OtpChallenge::query()->findOrFail($challengeId);

        $this->assertSame('09123456789', $message['mobile']);
        $this->assertSame($challengeId, $message['challenge_id']);
        $this->assertNotSame($message['code'], $challenge->code_digest);
        $this->assertSame(64, strlen($challenge->code_digest));
        $this->assertSame('sent', $challenge->delivery_status->value);
        $this->assertSame(1, $challenge->delivery_attempts);
        $this->assertSame('fake-'.$challengeId, $challenge->provider_message_id);

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'request_id' => $challengeId,
            'code' => $message['code'],
        ]);

        $verify
            ->assertOk()
            ->assertJsonPath('data.mobile', '09123456789')
            ->assertJsonPath('data.roles.0', 'customer');

        $user = User::query()->where('mobile', '09123456789')->firstOrFail();
        $this->assertNotNull($user->mobile_verified_at);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role' => 'customer',
            'scope_type' => 'global',
            'scope_id' => 'global',
        ]);
        $this->assertDatabaseCount('auth_sessions', 1);
        $this->assertTrue(AuthSession::query()->firstOrFail()->isActive());
        $this->assertNotNull($challenge->fresh()?->consumed_at);

        $this->postJson('/api/v1/auth/otp/verify', [
            'request_id' => $challengeId,
            'code' => $message['code'],
        ])->assertStatus(422)->assertJsonPath('error.code', 'auth.otp_invalid');
    }

    public function test_resend_is_blocked_until_the_authoritative_retry_window(): void
    {
        $payload = [
            'mobile' => '09120000001',
            'purpose' => 'login',
        ];

        $this->postJson('/api/v1/auth/otp/request', $payload)->assertAccepted();

        $this->postJson('/api/v1/auth/otp/request', $payload)
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'auth.otp_resend_too_soon');

        $this->assertDatabaseCount('otp_challenges', 1);
    }

    public function test_failed_attempts_are_committed_and_the_challenge_locks(): void
    {
        $request = $this->postJson('/api/v1/auth/otp/request', [
            'mobile' => '09120000002',
            'purpose' => 'login',
        ])->assertAccepted();

        $message = $this->sender->latest();
        $challengeId = (string) $request->json('data.request_id');
        $wrongCode = $message['code'] === '000000' ? '111111' : '000000';

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'request_id' => $challengeId,
                'code' => $wrongCode,
            ])->assertStatus(422)->assertJsonPath('error.code', 'auth.otp_invalid');
        }

        $this->postJson('/api/v1/auth/otp/verify', [
            'request_id' => $challengeId,
            'code' => $wrongCode,
        ])->assertStatus(429)->assertJsonPath('error.code', 'auth.otp_locked');

        $challenge = OtpChallenge::query()->findOrFail($challengeId);
        $this->assertSame(5, $challenge->attempts);
        $this->assertNotNull($challenge->locked_at);
        $this->assertDatabaseCount('users', 0);
    }
}
