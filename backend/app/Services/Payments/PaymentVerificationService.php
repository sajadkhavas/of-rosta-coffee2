<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use App\Data\Payments\PaymentVerificationResult;
use App\Enums\OrderStatus;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\ReservationStatus;
use App\Enums\StockReason;
use App\Exceptions\ApiDomainException;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentReconciliation;
use App\Models\ProductVariant;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PaymentVerificationService
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly PaymentEventRecorder $events,
        private readonly LedgerService $ledger,
        private readonly AuditRecorder $audit,
    ) {}

    public function verifyForUser(
        User $user,
        string $paymentId,
        Request $request,
    ): PaymentAttempt {
        $attempt = PaymentAttempt::query()
            ->where('user_id', $user->id)
            ->findOrFail($paymentId);

        return $this->verify($attempt, $user, $request);
    }

    public function verify(
        PaymentAttempt $attempt,
        ?User $actor = null,
        ?Request $request = null,
    ): PaymentAttempt {
        if ($attempt->status === PaymentStatus::Paid) {
            return $attempt->load('order');
        }

        $this->events->record(
            $attempt,
            PaymentEventType::VerificationRequested,
            ['source' => $actor === null ? 'callback_or_reconciliation' : 'customer'],
        );

        try {
            $result = $this->provider->verify($attempt);
        } catch (ApiDomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new ApiDomainException(
                'payment.verification_unavailable',
                'تأیید پرداخت موقتاً در دسترس نیست.',
                503,
            );
        }

        return $this->apply($attempt, $result, $actor, $request);
    }

    private function apply(
        PaymentAttempt $attempt,
        PaymentVerificationResult $result,
        ?User $actor,
        ?Request $request,
    ): PaymentAttempt {
        return DB::transaction(function () use (
            $attempt,
            $result,
            $actor,
            $request,
        ): PaymentAttempt {
            $locked = PaymentAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($locked->order_id);

            if (
                $result->amount !== $locked->amount
                || $result->currency !== $locked->currency
                || $locked->amount !== $order->grand_total
                || $locked->currency !== $order->currency
            ) {
                $this->reconciliation(
                    $locked,
                    $order,
                    'payment.amount_or_currency_mismatch',
                    'مبلغ یا ارز پاسخ درگاه با سفارش سازگار نیست.',
                    [
                        'expected_amount' => $locked->amount,
                        'actual_amount' => $result->amount,
                        'expected_currency' => $locked->currency,
                        'actual_currency' => $result->currency,
                    ],
                );

                throw new ApiDomainException(
                    'payment.verification_mismatch',
                    'اطلاعات تأیید پرداخت با سفارش سازگار نیست.',
                    409,
                );
            }

            if ($result->status === PaymentStatus::Paid) {
                return $this->applyPaid($locked, $order, $result, $actor, $request);
            }

            if ($locked->status === PaymentStatus::Paid) {
                return $locked->load('order');
            }

            $eventType = match ($result->status) {
                PaymentStatus::Failed => PaymentEventType::VerifiedFailed,
                PaymentStatus::Cancelled => PaymentEventType::VerifiedCancelled,
                PaymentStatus::Refunded => PaymentEventType::Refunded,
                default => PaymentEventType::VerificationRequested,
            };

            $updates = [
                'status' => $result->status,
                'provider_transaction_id' => $result->providerTransactionId,
                'provider_metadata' => $result->metadata,
            ];

            if ($result->status === PaymentStatus::Failed) {
                $updates['failed_at'] = now();
            } elseif ($result->status === PaymentStatus::Cancelled) {
                $updates['cancelled_at'] = now();
            } elseif ($result->status === PaymentStatus::Refunded) {
                $updates['refunded_at'] = $result->verifiedAt ?? now();
            }

            $locked->forceFill($updates)->save();
            $this->events->record(
                $locked,
                $eventType,
                $result->metadata,
                $result->providerEventId,
            );

            return $locked->refresh()->load('order');
        }, attempts: 3);
    }

    private function applyPaid(
        PaymentAttempt $payment,
        Order $order,
        PaymentVerificationResult $result,
        ?User $actor,
        ?Request $request,
    ): PaymentAttempt {
        if (
            $payment->status === PaymentStatus::Paid
            && $order->status === OrderStatus::Paid
        ) {
            return $payment->load('order');
        }

        if ($order->status !== OrderStatus::AwaitingPayment) {
            $this->reconciliation(
                $payment,
                $order,
                'payment.paid_for_non_payable_order',
                'درگاه پرداخت موفق را برای سفارشی با وضعیت غیرقابل پرداخت گزارش کرد.',
                ['order_status' => $order->status->value],
            );

            throw new ApiDomainException(
                'payment.reconciliation_required',
                'پرداخت نیازمند بررسی عملیاتی است.',
                409,
            );
        }

        $reservations = InventoryReservation::query()
            ->where('order_id', $order->id)
            ->where('status', ReservationStatus::Active->value)
            ->orderBy('variant_id')
            ->lockForUpdate()
            ->get();

        if (
            $reservations->isEmpty()
            || $reservations->contains(
                static fn (InventoryReservation $reservation): bool =>
                    $reservation->expires_at->isPast(),
            )
        ) {
            $this->reconciliation(
                $payment,
                $order,
                'payment.paid_after_reservation_expiry',
                'پرداخت پس از پایان یا حذف رزرو موجودی تأیید شده است.',
                [],
            );

            throw new ApiDomainException(
                'payment.reconciliation_required',
                'پرداخت نیازمند بررسی موجودی است.',
                409,
            );
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $reservations->pluck('variant_id')->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($reservations as $reservation) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($reservation->variant_id);
            if (
                ! $variant instanceof ProductVariant
                || $variant->stock_on_hand < $reservation->quantity
                || $variant->stock_reserved < $reservation->quantity
            ) {
                $this->reconciliation(
                    $payment,
                    $order,
                    'payment.stock_invariant_failed',
                    'موجودی برای مصرف رزرو پرداخت‌شده سازگار نیست.',
                    ['variant_id' => $reservation->variant_id],
                );

                throw new ApiDomainException(
                    'payment.reconciliation_required',
                    'پرداخت نیازمند بررسی موجودی است.',
                    409,
                );
            }

            $newOnHand = $variant->stock_on_hand - $reservation->quantity;
            $newReserved = $variant->stock_reserved - $reservation->quantity;

            $variant->forceFill([
                'stock_on_hand' => $newOnHand,
                'stock_reserved' => $newReserved,
            ])->save();

            StockLedgerEntry::query()->create([
                'variant_id' => $variant->id,
                'roast_batch_id' => $reservation->roast_batch_id,
                'actor_id' => $actor?->id,
                'delta' => -$reservation->quantity,
                'balance_after' => $newOnHand,
                'reason' => StockReason::OrderConsumed,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'idempotency_key' => 'payment:consume:'.$reservation->id,
                'metadata' => [
                    'payment_attempt_id' => $payment->id,
                    'reservation_id' => $reservation->id,
                ],
            ]);

            $reservation->forceFill([
                'status' => ReservationStatus::Consumed,
                'released_at' => now(),
            ])->save();
        }

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'provider_transaction_id' => $result->providerTransactionId,
            'provider_metadata' => $result->metadata,
            'verified_at' => $result->verifiedAt ?? now(),
            'failure_code' => null,
            'failure_message' => null,
        ])->save();

        $order->forceFill(['status' => OrderStatus::Paid])->save();
        $this->ledger->recordSale($payment, $order);

        $this->events->record(
            $payment,
            PaymentEventType::VerifiedPaid,
            $result->metadata,
            $result->providerEventId,
        );

        $this->audit->record(
            'payment.verified_paid',
            actor: $actor,
            auditable: $payment,
            metadata: [
                'order_id' => $order->id,
                'amount' => $payment->amount,
                'provider_transaction_id' => $result->providerTransactionId,
            ],
            request: $request,
        );

        return $payment->refresh()->load('order');
    }

    /**
     * @param array<string, mixed> $details
     */
    private function reconciliation(
        PaymentAttempt $payment,
        Order $order,
        string $code,
        string $message,
        array $details,
    ): PaymentReconciliation {
        $existing = PaymentReconciliation::query()
            ->where('payment_attempt_id', $payment->id)
            ->where('code', $code)
            ->where('status', ReconciliationStatus::Open->value)
            ->first();

        if ($existing instanceof PaymentReconciliation) {
            return $existing;
        }

        $record = PaymentReconciliation::query()->create([
            'payment_attempt_id' => $payment->id,
            'order_id' => $order->id,
            'status' => ReconciliationStatus::Open,
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ]);

        $this->events->record(
            $payment,
            PaymentEventType::ReconciliationRequired,
            ['code' => $code, 'details' => $details],
        );

        return $record;
    }
}
