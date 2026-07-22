<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ApiDomainException;
use App\Exceptions\PaymentProviderException;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

final class PaymentService
{
    public function __construct(
        private readonly PaymentProviderManager $providers,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @return array{attempt: PaymentAttempt, replayed: bool}
     *
     * @throws JsonException
     */
    public function initiate(
        User $user,
        string $orderId,
        string $idempotencyKey,
        Request $request,
    ): array {
        if (! $this->providers->ready()) {
            throw new ApiDomainException(
                'payment.unavailable',
                'درگاه پرداخت فعال یا آماده نیست.',
                503,
            );
        }

        $provider = $this->providers->current();
        $prepared = DB::transaction(function () use (
            $user,
            $orderId,
            $idempotencyKey,
            $provider,
        ): array {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $requestHash = hash('sha256', json_encode([
                'order_id' => $order->id,
                'provider' => $provider->name(),
                'amount' => $order->grand_total,
                'currency' => $order->currency,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            $existing = PaymentAttempt::query()
                ->where('order_id', $order->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PaymentAttempt) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new ApiDomainException(
                        'payment.idempotency_conflict',
                        'این کلید Idempotency قبلاً برای درخواست پرداخت دیگری استفاده شده است.',
                        409,
                    );
                }

                return $this->replayPreparedAttempt($existing, $order);
            }

            $active = PaymentAttempt::query()
                ->where('order_id', $order->id)
                ->whereIn('status', [
                    PaymentAttemptStatus::Initiated->value,
                    PaymentAttemptStatus::Pending->value,
                ])
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($active instanceof PaymentAttempt) {
                return $this->replayPreparedAttempt($active, $order);
            }

            $this->assertPayableLocked($order);
            $reservationExpiry = InventoryReservation::query()
                ->where('order_id', $order->id)
                ->where('status', ReservationStatus::Active->value)
                ->min('expires_at');
            $reservationExpiresAt = $reservationExpiry
                ? CarbonImmutable::parse((string) $reservationExpiry)
                : null;

            if (! $reservationExpiresAt || $reservationExpiresAt->isPast()) {
                throw new ApiDomainException(
                    'payment.reservation_expired',
                    'مهلت رزرو موجودی این سفارش پایان یافته است.',
                    409,
                );
            }

            $expiresAt = now()->addMinutes(
                max(1, (int) config('rosta.payment.attempt_ttl_minutes', 20)),
            );
            if ($reservationExpiresAt->lt($expiresAt)) {
                $expiresAt = $reservationExpiresAt;
            }

            $attempt = PaymentAttempt::query()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'provider' => $provider->name(),
                'attempt_number' => ((int) PaymentAttempt::query()
                    ->where('order_id', $order->id)
                    ->max('attempt_number')) + 1,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'status' => PaymentAttemptStatus::Initiated,
                'amount' => $order->grand_total,
                'amount_provider' => $order->grand_total
                    * max(1, (int) config('rosta.payment.amount_multiplier', 1)),
                'currency' => $order->currency,
                'expires_at' => $expiresAt,
            ]);

            return [
                'attempt' => $attempt,
                'order' => $order,
                'dispatch' => true,
                'replayed' => false,
            ];
        }, 3);

        if (! $prepared['dispatch']) {
            return [
                'attempt' => $prepared['attempt']->fresh(),
                'replayed' => true,
            ];
        }

        try {
            $result = $provider->initiate($prepared['attempt'], $prepared['order']);
        } catch (Throwable $exception) {
            $this->failInitiation($prepared['attempt'], $exception, $request);

            if ($exception instanceof ApiDomainException) {
                throw $exception;
            }

            throw new ApiDomainException(
                'payment.provider_unavailable',
                'اتصال به درگاه پرداخت انجام نشد.',
                503,
            );
        }

        $attempt = DB::transaction(function () use ($prepared, $result, $user, $request): PaymentAttempt {
            $locked = PaymentAttempt::query()
                ->whereKey($prepared['attempt']->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PaymentAttemptStatus::Initiated) {
                return $locked;
            }

            $locked->forceFill([
                'status' => PaymentAttemptStatus::Pending,
                'authority' => $result->authority,
                'redirect_url' => $result->redirectUrl,
                'gateway_code' => $result->gatewayCode,
                'request_payload' => $result->requestPayload,
                'response_payload' => $result->responsePayload,
            ])->save();

            $this->audit->record(
                'payment.initiated',
                actor: $user,
                auditable: $locked,
                metadata: [
                    'order_id' => $locked->order_id,
                    'provider' => $locked->provider,
                    'attempt_number' => $locked->attempt_number,
                    'amount' => $locked->amount,
                ],
                request: $request,
            );

            return $locked;
        }, 3);

        return ['attempt' => $attempt->fresh(), 'replayed' => false];
    }

    public function verifyForUser(
        User $user,
        string $paymentId,
        Request $request,
    ): PaymentAttempt {
        $attempt = PaymentAttempt::query()
            ->ownedBy($user)
            ->whereKey($paymentId)
            ->with('order')
            ->firstOrFail();

        if ($attempt->status->isTerminal()) {
            return $attempt;
        }

        if ($attempt->provider === 'testing') {
            return $this->verifyAttempt($attempt, 'OK', $request);
        }

        return $attempt;
    }

    public function handleProviderCallback(
        string $paymentId,
        ?string $authority,
        ?string $status,
        Request $request,
    ): PaymentAttempt {
        $attempt = PaymentAttempt::query()
            ->whereKey($paymentId)
            ->with('order')
            ->firstOrFail();

        if ($attempt->provider === 'zarinpal') {
            if (
                $authority === null
                || $attempt->authority === null
                || ! hash_equals($attempt->authority, trim($authority))
            ) {
                throw new ApiDomainException(
                    'payment.callback_mismatch',
                    'Authority بازگشت زرین‌پال با تلاش پرداخت سازگار نیست.',
                    409,
                );
            }
        } elseif (
            $authority !== null
            && $attempt->authority !== null
            && ! hash_equals($attempt->authority, trim($authority))
        ) {
            throw new ApiDomainException(
                'payment.callback_mismatch',
                'اطلاعات بازگشت درگاه با تلاش پرداخت سازگار نیست.',
                409,
            );
        }

        return $this->verifyAttempt($attempt, $status, $request);
    }

    private function verifyAttempt(
        PaymentAttempt $attempt,
        ?string $callbackStatus,
        Request $request,
    ): PaymentAttempt {
        $attempt->refresh()->load('order');
        if ($attempt->status->isTerminal()) {
            return $attempt;
        }

        $provider = $this->providers->for($attempt->provider);

        try {
            $result = $provider->verify($attempt, $attempt->order, $callbackStatus);
        } catch (Throwable $exception) {
            if ($exception instanceof ApiDomainException) {
                throw $exception;
            }

            throw new ApiDomainException(
                'payment.verification_unavailable',
                'امکان استعلام پرداخت از درگاه وجود ندارد.',
                503,
            );
        }

        if (! $result->verified) {
            return DB::transaction(function () use ($attempt, $result, $request): PaymentAttempt {
                $locked = PaymentAttempt::query()
                    ->whereKey($attempt->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status === PaymentAttemptStatus::Verified) {
                    return $locked;
                }

                $status = $result->state === 'cancelled'
                    ? PaymentAttemptStatus::Cancelled
                    : PaymentAttemptStatus::Failed;
                $locked->forceFill([
                    'status' => $status,
                    'gateway_code' => $result->gatewayCode,
                    'failure_code' => $result->failureCode,
                    'failure_message' => $result->message,
                    'verification_payload' => $result->payload,
                    'failed_at' => now(),
                    'cancelled_at' => $status === PaymentAttemptStatus::Cancelled ? now() : null,
                ])->save();

                $this->audit->record(
                    $status === PaymentAttemptStatus::Cancelled
                        ? 'payment.cancelled'
                        : 'payment.failed',
                    auditable: $locked,
                    metadata: [
                        'order_id' => $locked->order_id,
                        'provider' => $locked->provider,
                        'failure_code' => $locked->failure_code,
                    ],
                    request: $request,
                );

                return $locked;
            }, 3);
        }

        return DB::transaction(function () use ($attempt, $result, $request): PaymentAttempt {
            $lockedAttempt = PaymentAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status === PaymentAttemptStatus::Verified) {
                return $lockedAttempt;
            }

            $order = Order::query()
                ->whereKey($lockedAttempt->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedAttempt->amount !== $order->grand_total
                || $lockedAttempt->currency !== $order->currency
            ) {
                return $this->markReviewRequiredLocked(
                    $lockedAttempt,
                    $result->referenceId,
                    $result->gatewayCode,
                    $result->payload,
                    'amount_mismatch',
                    'مبلغ تأییدشده با مبلغ سفارش سازگار نیست.',
                    $request,
                );
            }

            if ($order->status === OrderStatus::Paid) {
                return $this->markReviewRequiredLocked(
                    $lockedAttempt,
                    $result->referenceId,
                    $result->gatewayCode,
                    $result->payload,
                    'duplicate_verified_payment',
                    'یک پرداخت دیگر قبلاً این سفارش را تسویه کرده است.',
                    $request,
                );
            }

            if ($order->status !== OrderStatus::AwaitingPayment) {
                return $this->markReviewRequiredLocked(
                    $lockedAttempt,
                    $result->referenceId,
                    $result->gatewayCode,
                    $result->payload,
                    'order_state_mismatch',
                    'پرداخت تأیید شد اما سفارش دیگر در وضعیت قابل تسویه نیست.',
                    $request,
                );
            }

            $reservations = InventoryReservation::query()
                ->where('order_id', $order->id)
                ->orderBy('variant_id')
                ->lockForUpdate()
                ->get();

            if (! $this->reservationsAreConsumable($reservations)) {
                return $this->markReviewRequiredLocked(
                    $lockedAttempt,
                    $result->referenceId,
                    $result->gatewayCode,
                    $result->payload,
                    'reservation_unavailable',
                    'پرداخت تأیید شد اما رزرو موجودی قابل مصرف نیست.',
                    $request,
                );
            }

            $variants = ProductVariant::query()
                ->whereIn('id', $reservations->pluck('variant_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($reservations as $reservation) {
                $variant = $variants->get($reservation->variant_id);
                if (
                    ! $variant instanceof ProductVariant
                    || $variant->stock_reserved < $reservation->quantity
                    || $variant->stock_on_hand < $reservation->quantity
                ) {
                    return $this->markReviewRequiredLocked(
                        $lockedAttempt,
                        $result->referenceId,
                        $result->gatewayCode,
                        $result->payload,
                        'inventory_reconciliation_required',
                        'پرداخت تأیید شد اما موجودی نیازمند تطبیق دستی است.',
                        $request,
                    );
                }
            }

            foreach ($reservations as $reservation) {
                /** @var ProductVariant $variant */
                $variant = $variants->get($reservation->variant_id);
                $variant->forceFill([
                    'stock_on_hand' => $variant->stock_on_hand - $reservation->quantity,
                    'stock_reserved' => $variant->stock_reserved - $reservation->quantity,
                ])->save();
                $reservation->forceFill([
                    'status' => ReservationStatus::Consumed,
                    'consumed_at' => now(),
                ])->save();
            }

            $order->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ])->save();

            $lockedAttempt->forceFill([
                'status' => PaymentAttemptStatus::Verified,
                'reference_id' => $result->referenceId,
                'gateway_code' => $result->gatewayCode,
                'failure_code' => null,
                'failure_message' => null,
                'verification_payload' => $result->payload,
                'verified_at' => now(),
            ])->save();

            $this->audit->record(
                'payment.verified',
                actor: $order->user,
                auditable: $lockedAttempt,
                metadata: [
                    'order_id' => $order->id,
                    'roastery_id' => $order->roastery_id,
                    'amount' => $order->grand_total,
                    'reference_id' => $lockedAttempt->reference_id,
                    'reservations_consumed' => $reservations->count(),
                ],
                request: $request,
            );

            return $lockedAttempt;
        }, 3);
    }

    /**
     * @return array{attempt: PaymentAttempt, order: Order, dispatch: false, replayed: true}
     */
    private function replayPreparedAttempt(
        PaymentAttempt $attempt,
        Order $order,
    ): array {
        if (
            $attempt->status === PaymentAttemptStatus::Pending
            && $attempt->redirect_url
            && (! $attempt->expires_at || $attempt->expires_at->isFuture())
        ) {
            return [
                'attempt' => $attempt,
                'order' => $order,
                'dispatch' => false,
                'replayed' => true,
            ];
        }

        if ($attempt->status === PaymentAttemptStatus::Initiated) {
            throw new ApiDomainException(
                'payment.in_progress',
                'درخواست پرداخت در حال اتصال به درگاه است.',
                409,
                headers: ['Retry-After' => '1'],
            );
        }

        throw new ApiDomainException(
            'payment.idempotency_terminal',
            'نتیجه این تلاش پرداخت نهایی شده است؛ وضعیت سفارش را بررسی کنید.',
            409,
        );
    }

    private function assertPayableLocked(Order $order): void
    {
        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw new ApiDomainException(
                'payment.order_not_payable',
                'این سفارش در وضعیت فعلی قابل پرداخت نیست.',
                409,
            );
        }
    }

    /**
     * @param  Collection<int, InventoryReservation>  $reservations
     */
    private function reservationsAreConsumable(Collection $reservations): bool
    {
        return $reservations->isNotEmpty()
            && $reservations->every(
                fn (InventoryReservation $reservation): bool =>
                    $reservation->status === ReservationStatus::Active
                    && $reservation->expires_at->isFuture(),
            );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markReviewRequiredLocked(
        PaymentAttempt $attempt,
        ?string $referenceId,
        ?string $gatewayCode,
        array $payload,
        string $failureCode,
        string $message,
        Request $request,
    ): PaymentAttempt {
        $attempt->forceFill([
            'status' => PaymentAttemptStatus::RequiresReview,
            'reference_id' => $referenceId,
            'gateway_code' => $gatewayCode,
            'failure_code' => $failureCode,
            'failure_message' => $message,
            'verification_payload' => $payload,
            'review_required_at' => now(),
        ])->save();

        $this->audit->record(
            'payment.review_required',
            auditable: $attempt,
            metadata: [
                'order_id' => $attempt->order_id,
                'provider' => $attempt->provider,
                'failure_code' => $failureCode,
                'reference_id' => $referenceId,
            ],
            request: $request,
        );

        return $attempt;
    }

    private function failInitiation(
        PaymentAttempt $attempt,
        Throwable $exception,
        Request $request,
    ): void {
        DB::transaction(function () use ($attempt, $exception, $request): void {
            $locked = PaymentAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== PaymentAttemptStatus::Initiated) {
                return;
            }

            $locked->forceFill([
                'status' => PaymentAttemptStatus::Failed,
                'failure_code' => $exception instanceof PaymentProviderException
                    ? ($exception->providerCode ?: 'provider_error')
                    : 'provider_unavailable',
                'failure_message' => mb_substr($exception->getMessage(), 0, 1000),
                'failed_at' => now(),
            ])->save();

            $this->audit->record(
                'payment.initiation_failed',
                auditable: $locked,
                metadata: [
                    'order_id' => $locked->order_id,
                    'provider' => $locked->provider,
                    'failure_code' => $locked->failure_code,
                ],
                request: $request,
            );
        }, 3);
    }
}
