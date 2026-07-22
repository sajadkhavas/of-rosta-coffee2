<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentProvider;
use App\Exceptions\ApiDomainException;
use App\Exceptions\PaymentProviderException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Data\PaymentInitiationResult;
use App\Services\Payments\Data\PaymentVerificationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class ZarinpalPaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'zarinpal';
    }

    public function initiate(
        PaymentAttempt $attempt,
        Order $order,
    ): PaymentInitiationResult {
        $callbackUrl = route('api.v1.payments.zarinpal.callback', [
            'paymentId' => $attempt->id,
        ]);
        $payload = [
            'merchant_id' => $this->merchantId(),
            'amount' => $attempt->amount_provider,
            'callback_url' => $callbackUrl,
            'description' => "پرداخت سفارش {$order->order_number} رستا",
        ];

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout($this->timeout())
                ->post((string) config('rosta.payment.zarinpal.request_url'), $payload);
        } catch (ConnectionException $exception) {
            throw new PaymentProviderException(
                'ارتباط با زرین‌پال هنگام ایجاد پرداخت برقرار نشد.',
                previous: $exception,
            );
        }

        $body = $response->json() ?? [];
        $code = (string) (data_get($body, 'data.code')
            ?? data_get($body, 'errors.code')
            ?? $response->status());
        $authority = trim((string) data_get($body, 'data.authority'));

        if (! $response->successful() || $code !== '100' || $authority === '') {
            throw new PaymentProviderException(
                (string) (data_get($body, 'errors.message')
                    ?: 'زرین‌پال درخواست پرداخت را نپذیرفت.'),
                $code,
            );
        }

        return new PaymentInitiationResult(
            authority: $authority,
            redirectUrl: rtrim((string) config('rosta.payment.zarinpal.start_pay_url'), '/').'/'.$authority,
            gatewayCode: $code,
            requestPayload: [
                'merchant_id' => '[REDACTED]',
                'amount' => $attempt->amount_provider,
                'callback_url' => $callbackUrl,
                'description' => $payload['description'],
            ],
            responsePayload: $this->sanitize($body),
        );
    }

    public function verify(
        PaymentAttempt $attempt,
        Order $order,
        ?string $callbackStatus,
    ): PaymentVerificationResult {
        if (Str::upper(trim((string) $callbackStatus)) !== 'OK') {
            return PaymentVerificationResult::failed(
                state: 'cancelled',
                failureCode: 'customer_cancelled',
                message: 'کاربر پرداخت را تکمیل نکرد.',
                gatewayCode: 'NOK',
                payload: ['status' => $callbackStatus],
            );
        }

        $payload = [
            'merchant_id' => $this->merchantId(),
            'amount' => $attempt->amount_provider,
            'authority' => $attempt->authority,
        ];

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout($this->timeout())
                ->post((string) config('rosta.payment.zarinpal.verify_url'), $payload);
        } catch (ConnectionException $exception) {
            throw new PaymentProviderException(
                'ارتباط با زرین‌پال هنگام تأیید پرداخت برقرار نشد.',
                previous: $exception,
            );
        }

        $body = $response->json() ?? [];
        $code = (string) (data_get($body, 'data.code')
            ?? data_get($body, 'errors.code')
            ?? $response->status());
        $sanitized = $this->sanitize($body);

        if ($response->successful() && in_array($code, ['100', '101'], true)) {
            return PaymentVerificationResult::verified(
                referenceId: (string) data_get($body, 'data.ref_id'),
                gatewayCode: $code,
                payload: $sanitized,
            );
        }

        return PaymentVerificationResult::failed(
            state: 'failed',
            failureCode: 'provider_rejected',
            message: (string) (data_get($body, 'errors.message')
                ?: 'تأیید تراکنش در زرین‌پال ناموفق بود.'),
            gatewayCode: $code,
            payload: $sanitized,
        );
    }

    private function merchantId(): string
    {
        $merchantId = trim((string) config('rosta.payment.zarinpal.merchant_id'));
        if ($merchantId === '') {
            throw new ApiDomainException(
                'payment.unavailable',
                'Merchant ID زرین‌پال تنظیم نشده است.',
                503,
            );
        }

        return $merchantId;
    }

    private function timeout(): int
    {
        return max(2, (int) config('rosta.payment.timeout_seconds', 10));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitize(array $payload): array
    {
        unset($payload['merchant_id']);

        if (isset($payload['data']) && is_array($payload['data'])) {
            unset($payload['data']['card_pan'], $payload['data']['card_hash']);
        }

        return $payload;
    }
}
