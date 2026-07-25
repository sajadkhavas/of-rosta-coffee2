<?php

namespace Tests\Feature;

use App\Services\Sms\AcceptanceOtpSender;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AcceptanceOtpBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sms.driver', 'acceptance');
    }

    public function test_acceptance_otp_is_encrypted_at_rest_and_consumed_once(): void
    {
        $challengeId = (string) Str::ulid();
        $sender = new AcceptanceOtpSender;

        $sender->send('09123456789', '482731', 'login', $challengeId);

        $stored = Cache::get(AcceptanceOtpSender::CACHE_PREFIX.$challengeId);
        $this->assertIsString($stored);
        $this->assertNotSame('482731', $stored);
        $this->assertStringNotContainsString('482731', $stored);

        $this->assertSame(0, Artisan::call('rosta:acceptance-otp', [
            'challenge_id' => $challengeId,
        ]));
        $this->assertSame('482731', trim(Artisan::output()));
        $this->assertFalse(Cache::has(AcceptanceOtpSender::CACHE_PREFIX.$challengeId));

        $this->assertSame(1, Artisan::call('rosta:acceptance-otp', [
            'challenge_id' => $challengeId,
        ]));
        $this->assertStringContainsString('missing, expired or already consumed', Artisan::output());
    }

    public function test_acceptance_otp_rejects_invalid_identifiers(): void
    {
        $this->assertSame(2, Artisan::call('rosta:acceptance-otp', [
            'challenge_id' => '../invalid',
        ]));
        $this->assertStringContainsString('valid challenge ULID', Artisan::output());
    }

    public function test_acceptance_otp_bridge_is_forbidden_outside_testing(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        $sender = new AcceptanceOtpSender;
        $this->assertFalse($sender->isAvailable());

        $this->assertSame(1, Artisan::call('rosta:acceptance-otp', [
            'challenge_id' => (string) Str::ulid(),
        ]));
        $this->assertStringContainsString('restricted to the testing driver', Artisan::output());
    }
}
