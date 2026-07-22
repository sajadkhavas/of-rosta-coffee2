<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Exceptions\ApiDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StartPaymentRequest;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function request(
        StartPaymentRequest $request,
        PaymentService $payments,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $result = $payments->initiate(
            $user,
            (string) $request->validated('order_id'),
            (string) $request->validated('idempotency_key'),
            $request,
        );
        $attempt = $result['attempt'];

        if (! $attempt->redirect_url) {
            throw new ApiDomainException(
                'payment.redirect_unavailable',
                'آدرس انتقال به درگاه در دسترس نیست.',
                503,
            );
        }

        return ApiResponse::success([
            'payment_id' => $attempt->id,
            'redirect_url' => $attempt->redirect_url,
        ], $result['replayed'] ? 200 : 201);
    }

    public function verify(
        Request $request,
        string $paymentId,
        PaymentService $payments,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $attempt = $payments->verifyForUser(
            $user,
            $paymentId,
            $request,
        );

        return ApiResponse::success($this->verificationPayload($attempt));
    }

    public function callback(
        Request $request,
        string $paymentId,
        PaymentService $payments,
    ): RedirectResponse {
        $attempt = $payments->handleProviderCallback(
            $paymentId,
            $this->nullableQuery($request, 'Authority', 'authority'),
            $this->nullableQuery($request, 'Status', 'status'),
            $request,
        );

        return redirect()->away($this->frontendCallbackUrl($attempt));
    }

    /**
     * @return array<string, mixed>
     */
    private function verificationPayload(PaymentAttempt $attempt): array
    {
        $attempt->loadMissing('order');

        return [
            'payment_id' => $attempt->id,
            'status' => $this->publicStatus($attempt),
            'order_id' => $attempt->order_id,
            'order_status' => $attempt->order->status->value,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'verified_at' => $attempt->verified_at?->toIso8601String(),
        ];
    }

    private function publicStatus(PaymentAttempt $attempt): string
    {
        return match ($attempt->status) {
            PaymentAttemptStatus::Verified => 'paid',
            PaymentAttemptStatus::Failed => 'failed',
            PaymentAttemptStatus::Cancelled => 'cancelled',
            PaymentAttemptStatus::Refunded => 'refunded',
            default => 'pending',
        };
    }

    private function frontendCallbackUrl(PaymentAttempt $attempt): string
    {
        $configured = trim((string) config('rosta.payment.frontend_callback_url'));
        $parts = parse_url($configured);
        if (! is_array($parts)) {
            throw new ApiDomainException(
                'payment.callback_configuration_invalid',
                'آدرس بازگشت پرداخت معتبر نیست.',
                503,
            );
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $configuredHosts = config('rosta.allowed_payment_redirect_hosts', []);
        $allowedHosts = is_array($configuredHosts)
            ? array_values(array_filter(array_map(
                static fn (mixed $value): string => strtolower(trim((string) $value)),
                $configuredHosts,
            )))
            : [];
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if (
            $configured === ''
            || ! in_array($scheme, ['https', 'http'], true)
            || ($scheme !== 'https' && ! $local)
            || ! in_array($host, $allowedHosts, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new ApiDomainException(
                'payment.callback_configuration_invalid',
                'آدرس بازگشت پرداخت معتبر نیست.',
                503,
            );
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query = [
            ...$query,
            'payment_id' => $attempt->id,
            'order_id' => $attempt->order_id,
            'status' => $this->publicStatus($attempt),
        ];

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) && $parts['path'] !== ''
            ? $parts['path']
            : '/checkout';

        return $scheme.'://'.$host.$port.$path.'?'.http_build_query(
            $query,
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
    }

    private function nullableQuery(Request $request, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->query($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
