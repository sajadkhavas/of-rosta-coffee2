<?php

namespace Tests\Feature;

use App\Exceptions\KavenegarDeliveryException;
use App\Services\Notifications\Providers\KavenegarSmsProvider;
use App\Services\Sms\KavenegarOtpSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class KavenegarOtpProviderTest extends TestCase
{
    private const API_KEY = 'provider-key-1234567890abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rosta.otp.enabled' => true,
            'rosta.otp.driver' => 'kavenegar',
            'rosta.otp.kavenegar.templates' => [
                'login' => 'rosta-login',
                'register' => 'rosta-register',
                'verify_mobile' => 'rosta-mobile',
            ],
            'rosta.kavenegar.api_key' => self::API_KEY,
            'rosta.kavenegar.base_url' => 'https://api.example.test/v1',
            'rosta.kavenegar.connect_timeout_seconds' => 2,
            'rosta.kavenegar.timeout_seconds' => 4,
            'rosta.kavenegar.circuit_failure_threshold' => 20,
            'rosta.kavenegar.circuit_window_seconds' => 300,
            'rosta.kavenegar.circuit_open_seconds' => 120,
            'rosta.kavenegar.retry_base_seconds' => 30,
        ]);
    }

    public function test_verify_lookup_success_returns_provider_message_id(): void
    {
        Http::fake([
            '*' => Http::response([
                'return' => ['status' => 200, 'message' => 'تایید شد'],
                'entries' => [['messageid' => 987654321]],
            ]),
        ]);

        $messageId = app(KavenegarOtpSender::class)->send(
            '+989123456789',
            '482731',
            'login',
            '01J00000000000000000000000',
        );

        $this->assertSame('987654321', $messageId);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/'.self::API_KEY.'/verify/lookup.json')
                && $request['receptor'] === '09123456789'
                && $request['token'] === '482731'
                && $request['template'] === 'rosta-login'
                && $request['type'] === 'sms';
        });
    }

    public function test_order_sms_uses_the_same_single_attempt_validated_client(): void
    {
        config(['rosta.notifications.kavenegar.sender' => '1000123456']);
        Http::fake([
            '*' => Http::response([
                'return' => ['status' => 200, 'message' => 'تایید شد'],
                'entries' => [['messageid' => 'order-message-42']],
            ]),
        ]);

        $messageId = app(KavenegarSmsProvider::class)->send(
            '00989123456789',
            'پیام سفارش رستا',
        );

        $this->assertSame('order-message-42', $messageId);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/'.self::API_KEY.'/sms/send.json')
                && $request['receptor'] === '09123456789'
                && $request['sender'] === '1000123456'
                && $request['message'] === 'پیام سفارش رستا';
        });
    }

    public function test_provider_rejection_is_terminal_and_redacted(): void
    {
        Http::fake([
            '*' => Http::response([
                'return' => ['status' => 418, 'message' => 'account credit details'],
                'entries' => [],
            ]),
        ]);

        $exception = $this->captureFailure();

        $this->assertSame('provider_rejected', $exception->reasonCode);
        $this->assertFalse($exception->retryable);
        $this->assertFalse($exception->ambiguous);
        $this->assertRedacted($exception);
        Http::assertSentCount(1);
    }

    public function test_invalid_mobile_is_rejected_before_any_http_request(): void
    {
        Http::fake();

        try {
            app(KavenegarOtpSender::class)->send(
                '02112345678',
                '482731',
                'login',
                '01J00000000000000000000000',
            );
            $this->fail('Expected the invalid mobile to be rejected.');
        } catch (KavenegarDeliveryException $exception) {
            $this->assertSame('otp_payload_invalid', $exception->reasonCode);
        }

        Http::assertNothingSent();
    }

    public function test_http_429_is_explicitly_retryable_without_an_inline_retry(): void
    {
        Http::fake([
            '*' => Http::response([], 429, ['Retry-After' => '17']),
        ]);

        $exception = $this->captureFailure();

        $this->assertSame('http_rate_limited', $exception->reasonCode);
        $this->assertTrue($exception->retryable);
        $this->assertFalse($exception->ambiguous);
        $this->assertSame(17, $exception->retryAfterSeconds);
        Http::assertSentCount(1);
    }

    public function test_http_5xx_is_unknown_and_never_retried_inline(): void
    {
        Http::fake(['*' => Http::response('upstream error', 503)]);

        $exception = $this->captureFailure();

        $this->assertSame('http_server_outcome_unknown', $exception->reasonCode);
        $this->assertFalse($exception->retryable);
        $this->assertTrue($exception->ambiguous);
        $this->assertRedacted($exception);
        Http::assertSentCount(1);
    }

    public function test_repeated_transport_failures_open_the_circuit_before_another_send(): void
    {
        config(['rosta.kavenegar.circuit_failure_threshold' => 2]);
        Http::fake(['*' => Http::response('upstream error', 503)]);

        $this->assertSame('http_server_outcome_unknown', $this->captureFailure()->reasonCode);
        $this->assertSame('http_server_outcome_unknown', $this->captureFailure()->reasonCode);

        $exception = $this->captureFailure();

        $this->assertSame('circuit_open', $exception->reasonCode);
        $this->assertTrue($exception->retryable);
        $this->assertFalse($exception->ambiguous);
        $this->assertGreaterThan(0, $exception->retryAfterSeconds);
        Http::assertSentCount(2);
    }

    public function test_connection_timeout_is_unknown_and_redacted(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $exception = $this->captureFailure();

        $this->assertSame('connection_outcome_unknown', $exception->reasonCode);
        $this->assertTrue($exception->ambiguous);
        $this->assertRedacted($exception);
        Http::assertSentCount(1);
    }

    public function test_malformed_success_response_is_unknown(): void
    {
        Http::fake([
            '*' => Http::response('not-json', 200, ['Content-Type' => 'application/json']),
        ]);

        $exception = $this->captureFailure();

        $this->assertSame('response_malformed', $exception->reasonCode);
        $this->assertTrue($exception->ambiguous);
        Http::assertSentCount(1);
    }

    private function captureFailure(): KavenegarDeliveryException
    {
        try {
            app(KavenegarOtpSender::class)->send(
                '09123456789',
                '482731',
                'login',
                '01J00000000000000000000000',
            );
        } catch (KavenegarDeliveryException $exception) {
            return $exception;
        }

        $this->fail('Expected Kavenegar delivery to fail.');
    }

    private function assertRedacted(KavenegarDeliveryException $exception): void
    {
        $serialized = serialize($exception);

        $this->assertStringNotContainsString(self::API_KEY, $serialized);
        $this->assertStringNotContainsString('09123456789', $serialized);
        $this->assertStringNotContainsString('482731', $serialized);
        $this->assertNull($exception->getPrevious());
    }
}
