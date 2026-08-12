<?php

namespace Tests\Feature;

use App\Contracts\OtpSender;
use App\Enums\OtpDeliveryStatus;
use App\Jobs\SendOtpCode;
use App\Models\OtpChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class OtpDeliverySafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_provider_outcome_is_never_sent_twice(): void
    {
        $sender = new class implements OtpSender
        {
            public int $attempts = 0;

            public function isAvailable(): bool
            {
                return true;
            }

            public function send(
                string $mobile,
                string $code,
                string $purpose,
                string $challengeId,
            ): ?string {
                $this->attempts++;

                throw new RuntimeException('Simulated ambiguous provider outcome.');
            }
        };
        $this->app->instance(OtpSender::class, $sender);

        $response = $this->postJson('/api/v1/auth/otp/request', [
            'mobile' => '09123456789',
            'purpose' => 'login',
        ])->assertAccepted();

        $challenge = OtpChallenge::query()->findOrFail(
            (string) $response->json('data.request_id'),
        );
        $this->assertSame(1, $sender->attempts);
        $this->assertSame(OtpDeliveryStatus::Unknown, $challenge->delivery_status);
        $this->assertSame('sender_outcome_unknown', $challenge->delivery_error_code);
        $this->assertNull($challenge->locked_at);

        SendOtpCode::forPlaintextCode(
            $challenge->id,
            $challenge->mobile,
            $challenge->purpose,
            '123456',
        )->handle($sender);

        $this->assertSame(1, $sender->attempts);
        $this->assertSame(
            OtpDeliveryStatus::Unknown,
            $challenge->fresh()?->delivery_status,
        );
    }
}
