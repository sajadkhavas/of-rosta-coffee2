<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Providers\ZarinpalPaymentProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ZarinpalPaymentProviderTest extends TestCase
{
    public function test_initiation_and_verification_use_server_side_amount_and_sanitize_card_data(): void
    {
        config([
            'rosta.payment.zarinpal.merchant_id' => '00000000-0000-0000-0000-000000000000',
            'rosta.payment.zarinpal.request_url' => 'https://gateway.test/request',
            'rosta.payment.zarinpal.verify_url' => 'https://gateway.test/verify',
            'rosta.payment.zarinpal.start_pay_url' => 'https://gateway.test/start',
            'rosta.payment.timeout_seconds' => 5,
        ]);
        Http::fake([
            'https://gateway.test/request' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A000000000000000000000000000001'],
                'errors' => [],
            ]),
            'https://gateway.test/verify' => Http::response([
                'data' => [
                    'code' => 100,
                    'ref_id' => 123456789,
                    'card_pan' => '0000-0000-0000-0000',
                    'card_hash' => 'secret-card-hash',
                ],
                'errors' => [],
            ]),
        ]);

        $attempt = new PaymentAttempt([
            'amount_provider' => 4_300_000,
            'authority' => null,
        ]);
        $attempt->id = '01TESTPAYMENT00000000000001';
        $order = new Order(['order_number' => 'R-TEST-0001']);
        $order->id = '01TESTORDER0000000000000001';
        $provider = app(ZarinpalPaymentProvider::class);

        $initiation = $provider->initiate($attempt, $order);
        $this->assertSame('A000000000000000000000000000001', $initiation->authority);
        $this->assertSame(
            'https://gateway.test/start/A000000000000000000000000000001',
            $initiation->redirectUrl,
        );
        $this->assertSame('[REDACTED]', $initiation->requestPayload['merchant_id']);

        $attempt->authority = $initiation->authority;
        $verification = $provider->verify($attempt, $order, 'OK');
        $this->assertTrue($verification->verified);
        $this->assertSame('123456789', $verification->referenceId);
        $this->assertArrayNotHasKey('card_pan', $verification->payload['data']);
        $this->assertArrayNotHasKey('card_hash', $verification->payload['data']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://gateway.test/request'
                && $request['amount'] === 4_300_000
                && $request['description'] === 'پرداخت سفارش R-TEST-0001 رستا';
        });
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://gateway.test/verify'
                && $request['amount'] === 4_300_000
                && $request['authority'] === 'A000000000000000000000000000001';
        });
    }

    public function test_non_ok_callback_is_cancelled_without_calling_provider_verify(): void
    {
        config([
            'rosta.payment.zarinpal.merchant_id' => '00000000-0000-0000-0000-000000000000',
        ]);
        Http::fake();
        $attempt = new PaymentAttempt([
            'amount_provider' => 1_000_000,
            'authority' => 'AUTHORITY',
        ]);
        $order = new Order(['order_number' => 'R-TEST-0002']);

        $result = app(ZarinpalPaymentProvider::class)
            ->verify($attempt, $order, 'NOK');

        $this->assertFalse($result->verified);
        $this->assertSame('cancelled', $result->state);
        $this->assertSame('customer_cancelled', $result->failureCode);
        Http::assertNothingSent();
    }
}
