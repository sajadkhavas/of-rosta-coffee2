<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Growth\PartnerCommissionEventService;
use App\Services\Notifications\NotificationOutboxService;

final class OrderObserver
{
    public function __construct(
        private readonly NotificationOutboxService $outbox,
        private readonly PartnerCommissionEventService $commissionEvents,
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
            $this->commissionEvents->recordPaidOrder($order);
        }
    }
}
