<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use App\Enums\OrderStatus;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ApiDomainException;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Checkout\CheckoutHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PaymentAttemptService
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly CheckoutHasher $hasher,
        private readonly PaymentEventRecorder $events,
        private readonly AuditRecorder $audit,
    ) {}

    public function request(
        User $user,
        string $orderId,
        string $idempotencyKey,
        Request $request,
    ): PaymentAttempt {
        $requestHash = $this->hasher->hash(['order_id' => $orderId]);

        $attempt = DB::transaction(function () use (
            $user,
            $orderId,
            $idempotencyKey,
            $requestHash,
        ): PaymentAttempt {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $existing = PaymentAttempt::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PaymentAttempt) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new ApiDomainException(
                        'payment.idempotency_conflict',
                        'این کلید Idempotency قبلاً برای سفارش دیگری استفاده شده است.',
                        409,
                    );
                }

                if ($existing->redirect_url !== null) {
                    return $existing;
                }

                if ($existing->status === PaymentStatus::Failed) {
                    throw new ApiDomainException(
                        $existing->failure_code ?? 'payment.request_failed',
                        $existing->failure_message ?? 'ایجاد پرداخت ناموفق بود.',
                        503,
                    );
                }

                return $existing;
            }

            $order = Order::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->status !== OrderStatus::AwaitingPayment) {
                throw new ApiDomainException(
                    'payment.order_not_payable',
                    'سفارش در وضعیت قابل پرداخت نیست.',
                    409,
                );
            }

            $activeReservations = InventoryReservation::query()
                ->where('order_id', $order->id)
                ->where('status', ReservationStatus::Active->value)
                ->where('expires_at', '>', now())
                ->count();

            if ($activeReservations !== $order->items()->count()) {
                throw new ApiDomainException(
                    'payment.reservation_expired',
                    'رزرو موجودی سفارش معتبر نیست.',
                    409,
                );
            }

            $attempt = PaymentAttempt::query()->create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'provider' => $this->provider->name(),
                'status' => PaymentStatus::Pending,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
            ]);

            $this->events->record(
                $attempt,
                PaymentEventType::Requested,
                ['order_id' => $order->id, 'amount' => $order->grand_total],
            );

            return $attempt;
        }, attempts: 3);

        if ($attempt->redirect_url !== null) {
            return $attempt;
        }

        try {
            $result = $this->provider->initiate($attempt);
            $this->assertSafeRedirect($result->redirectUrl);

            return DB::transaction(function () use ($attempt, $result, $user, $request): PaymentAttempt {
                $locked = PaymentAttempt::query()
                    ->lockForUpdate()
                    ->findOrFail($attempt->id);

                if ($locked->redirect_url === null) {
                    $locked->forceFill([
                        'redirect_url' => $result->redirectUrl,
                        'provider_authority' => $result->providerAuthority,
                        'provider_metadata' => $result->metadata,
                        'initiated_at' => now(),
                    ])->save();

                    $this->events->record(
                        $locked,
                        PaymentEventType::RedirectIssued,
                        ['provider_authority' => $result->providerAuthority],
                    );

                    $this->audit->record(
                        'payment.attempt.initiated',
                        actor: $user,
                        auditable: $locked,
                        metadata: [
                            'order_id' => $locked->order_id,
                            'amount' => $locked->amount,
                            'provider' => $locked->provider,
                        ],
                        request: $request,
                    );
                }

                return $locked->refresh();
            });
        } catch (ApiDomainException $exception) {
            $this->markFailed($attempt, $exception->errorCode, $exception->getMessage());
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($attempt, 'payment.provider_unavailable', 'ارتباط با درگاه پرداخت برقرار نشد.');

            throw new ApiDomainException(
                'payment.provider_unavailable',
                'ارتباط با درگاه پرداخت برقرار نشد.',
                503,
            );
        }
    }

    private function markFailed(
        PaymentAttempt $attempt,
        string $code,
        string $message,
    ): void {
        DB::transaction(function () use ($attempt, $code, $message): void {
            $locked = PaymentAttempt::query()->lockForUpdate()->find($attempt->id);
            if (! $locked instanceof PaymentAttempt || $locked->status !== PaymentStatus::Pending) {
                return;
            }

            $locked->forceFill([
                'status' => PaymentStatus::Failed,
                'failure_code' => $code,
                'failure_message' => $message,
                'failed_at' => now(),
            ])->save();

            $this->events->record(
                $locked,
                PaymentEventType::VerifiedFailed,
                ['failure_code' => $code],
            );
        });
    }

    private function assertSafeRedirect(string $redirectUrl): void
    {
        $parts = parse_url($redirectUrl);
        $host = is_array($parts) && isset($parts['host'])
            ? strtolower((string) $parts['host'])
            : null;
        $scheme = is_array($parts) && isset($parts['scheme'])
            ? strtolower((string) $parts['scheme'])
            : null;
        $allowedHosts = (array) config('rosta.allowed_payment_redirect_hosts', []);
        $localHosts = ['localhost', '127.0.0.1', '::1'];
        $schemeAllowed = $scheme === 'https'
            || ($scheme === 'http' && $host !== null && in_array($host, $localHosts, true));

        if (
            $host === null
            || ! $schemeAllowed
            || isset($parts['user'], $parts['pass'])
            || ! in_array($host, $allowedHosts, true)
        ) {
            throw new ApiDomainException(
                'payment.redirect_not_allowed',
                'نشانی انتقال درگاه مورد تأیید نیست.',
                502,
            );
        }
    }
}
