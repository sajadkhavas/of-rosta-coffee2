<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\InventoryReservation;
use App\Models\Order;

final class ReservationExpiryService
{
    public function __construct(
        private readonly OrderCancellationService $cancellations,
    ) {}

    public function expireDue(int $limit = 200): int
    {
        $orderIds = InventoryReservation::query()
            ->where('status', ReservationStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit(max(1, min(1000, $limit)))
            ->pluck('order_id')
            ->unique()
            ->values();

        $expired = 0;

        foreach ($orderIds as $orderId) {
            $order = Order::query()
                ->where('status', OrderStatus::AwaitingPayment->value)
                ->find($orderId);

            if (! $order instanceof Order) {
                continue;
            }

            $this->cancellations->expire($order);
            $expired++;
        }

        return $expired;
    }
}
