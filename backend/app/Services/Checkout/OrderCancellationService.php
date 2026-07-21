<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\ApiDomainException;
use App\Models\CheckoutQuote;
use App\Models\Coupon;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OrderCancellationService
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OrderService $orders,
    ) {}

    public function cancelByCustomer(
        User $user,
        string $orderId,
        ?string $reason,
        Request $request,
    ): Order {
        return DB::transaction(function () use ($user, $orderId, $reason, $request): Order {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->status === OrderStatus::Cancelled) {
                return $this->orders->loadOrder($order);
            }

            if ($order->status !== OrderStatus::AwaitingPayment) {
                throw new ApiDomainException(
                    'order.cancellation_not_allowed',
                    'این سفارش در وضعیت فعلی قابل لغو مستقیم نیست.',
                    409,
                );
            }

            $this->releaseReservations($order, ReservationStatus::Released);
            $couponReleased = $this->releaseCouponReservation($order);

            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            $order->subOrder()->update([
                'status' => SubOrderStatus::Cancelled->value,
            ]);

            $this->audit->record(
                'checkout.order.cancelled',
                actor: $user,
                auditable: $order,
                metadata: [
                    'reason' => $reason,
                    'coupon_reservation_released' => $couponReleased,
                ],
                request: $request,
            );

            return $this->orders->loadOrder($order->refresh());
        }, attempts: 3);
    }

    public function expire(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== OrderStatus::AwaitingPayment) {
                return;
            }

            $this->releaseReservations($locked, ReservationStatus::Expired);
            $couponReleased = $this->releaseCouponReservation($locked);

            $locked->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => 'reservation_expired',
            ])->save();

            $locked->subOrder()->update([
                'status' => SubOrderStatus::Cancelled->value,
            ]);

            $this->audit->record(
                'checkout.order.reservation_expired',
                auditable: $locked,
                metadata: [
                    'reason' => 'reservation_expired',
                    'coupon_reservation_released' => $couponReleased,
                ],
            );
        }, attempts: 3);
    }

    private function releaseReservations(
        Order $order,
        ReservationStatus $releasedStatus,
    ): void {
        $reservations = InventoryReservation::query()
            ->where('order_id', $order->id)
            ->where('status', ReservationStatus::Active->value)
            ->orderBy('variant_id')
            ->lockForUpdate()
            ->get();

        $variants = ProductVariant::query()
            ->whereIn('id', $reservations->pluck('variant_id')->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($reservations as $reservation) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($reservation->variant_id);
            if ($variant instanceof ProductVariant) {
                $variant->forceFill([
                    'stock_reserved' => max(
                        0,
                        $variant->stock_reserved - $reservation->quantity,
                    ),
                ])->save();
            }

            $reservation->forceFill([
                'status' => $releasedStatus,
                'released_at' => now(),
            ])->save();
        }
    }

    private function releaseCouponReservation(Order $order): bool
    {
        $quote = CheckoutQuote::query()
            ->lockForUpdate()
            ->find($order->quote_id);

        if (! $quote instanceof CheckoutQuote || $quote->coupon_id === null) {
            return false;
        }

        $coupon = Coupon::query()
            ->lockForUpdate()
            ->find($quote->coupon_id);

        if (! $coupon instanceof Coupon || $coupon->redemption_count <= 0) {
            return false;
        }

        $coupon->forceFill([
            'redemption_count' => $coupon->redemption_count - 1,
        ])->save();

        return true;
    }
}
