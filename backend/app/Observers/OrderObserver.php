<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Notifications\NotificationOutboxService;

final class OrderObserver
{
    public function __construct(
        private readonly NotificationOutboxService $outbox,
    ) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        if ($order->status === OrderStatus::Paid) {
            $this->outbox->queueOrder(
                $order,
                'order.paid',
                deduplicationKey: "order:{$order->id}:paid",
            );
        }
    }
}
