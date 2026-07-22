<?php

namespace App\Services\Fulfillment;

use App\Enums\OrderStatus;
use App\Enums\StockReason;
use App\Enums\SubOrderStatus;
use App\Exceptions\ApiDomainException;
use App\Models\InventoryRestock;
use App\Models\Order;
use App\Models\OrderInternalNote;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\Shipment;
use App\Models\StockLedgerEntry;
use App\Models\SubOrder;
use App\Models\SubOrderStatusHistory;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Notifications\NotificationOutboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FulfillmentService
{
    public function __construct(
        private readonly NotificationOutboxService $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{status: string, reason?: string|null, carrier?: string|null, tracking_code?: string|null, internal_note?: string|null}  $input
     */
    public function transition(
        User $actor,
        Roastery $roastery,
        string $orderId,
        array $input,
        Request $request,
    ): Order {
        $target = SubOrderStatus::from($input['status']);

        return DB::transaction(function () use (
            $actor,
            $roastery,
            $orderId,
            $input,
            $target,
            $request,
        ): Order {
            $order = Order::query()
                ->where('roastery_id', $roastery->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();
            $subOrder = SubOrder::query()
                ->where('order_id', $order->id)
                ->where('roastery_id', $roastery->id)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $subOrder->status;
            $this->assertTransitionAllowed($order, $from, $target);

            match ($target) {
                SubOrderStatus::Accepted => $this->accept($order, $subOrder, $actor),
                SubOrderStatus::Rejected => $this->reject(
                    $order,
                    $subOrder,
                    $actor,
                    trim((string) ($input['reason'] ?? '')),
                ),
                SubOrderStatus::Preparing => $this->prepare($order, $subOrder, $actor),
                SubOrderStatus::ReadyToShip => $this->readyToShip($order, $subOrder, $actor),
                SubOrderStatus::Shipped => $this->ship(
                    $order,
                    $subOrder,
                    $actor,
                    trim((string) ($input['carrier'] ?? '')),
                    trim((string) ($input['tracking_code'] ?? '')),
                ),
                SubOrderStatus::Delivered => $this->deliver($order, $subOrder, $actor),
                default => throw new ApiDomainException(
                    'fulfillment.transition_unsupported',
                    'این تغییر وضعیت پشتیبانی نمی‌شود.',
                    422,
                ),
            };

            $internalNote = trim((string) ($input['internal_note'] ?? ''));
            if ($internalNote !== '') {
                OrderInternalNote::query()->create([
                    'order_id' => $order->id,
                    'sub_order_id' => $subOrder->id,
                    'actor_id' => $actor->id,
                    'body' => $internalNote,
                ]);
            }

            $this->audit->record(
                'fulfillment.transitioned',
                actor: $actor,
                auditable: $subOrder,
                metadata: [
                    'order_id' => $order->id,
                    'roastery_id' => $roastery->id,
                    'from_status' => $from->value,
                    'requested_status' => $target->value,
                    'current_status' => $subOrder->status->value,
                ],
                request: $request,
            );

            return $order->fresh([
                'user',
                'roastery',
                'subOrder.roastery',
                'subOrder.items',
                'subOrder.shipment',
                'subOrder.statusHistory.actor',
            ]) ?? $order;
        }, 3);
    }

    private function assertTransitionAllowed(
        Order $order,
        SubOrderStatus $from,
        SubOrderStatus $to,
    ): void {
        $allowed = match ($from) {
            SubOrderStatus::PendingAcceptance => [
                SubOrderStatus::Accepted,
                SubOrderStatus::Rejected,
            ],
            SubOrderStatus::Accepted => [SubOrderStatus::Preparing],
            SubOrderStatus::Preparing => [SubOrderStatus::ReadyToShip],
            SubOrderStatus::ReadyToShip => [SubOrderStatus::Shipped],
            SubOrderStatus::Shipped => [SubOrderStatus::Delivered],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new ApiDomainException(
                'fulfillment.transition_invalid',
                "تغییر وضعیت از {$from->value} به {$to->value} مجاز نیست.",
                409,
            );
        }

        if (
            in_array($from, [SubOrderStatus::PendingAcceptance, SubOrderStatus::Accepted], true)
            && ! in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing], true)
        ) {
            throw new ApiDomainException(
                'fulfillment.order_not_paid',
                'عملیات روستری فقط برای سفارش پرداخت‌شده مجاز است.',
                409,
            );
        }
    }

    private function accept(
        Order $order,
        SubOrder $subOrder,
        User $actor,
    ): void {
        $this->setStatus(
            $subOrder,
            SubOrderStatus::Accepted,
            $actor,
            timestamps: ['accepted_at' => now()],
        );
        $order->forceFill(['status' => OrderStatus::Processing])->save();
        $this->outbox->queueOrder(
            $order,
            'order.accepted',
            subOrder: $subOrder,
            deduplicationKey: "sub-order:{$subOrder->id}:accepted",
        );
    }

    private function reject(
        Order $order,
        SubOrder $subOrder,
        User $actor,
        string $reason,
    ): void {
        if ($reason === '') {
            throw new ApiDomainException(
                'fulfillment.rejection_reason_required',
                'دلیل رد سفارش الزامی است.',
                422,
            );
        }

        $this->setStatus(
            $subOrder,
            SubOrderStatus::Rejected,
            $actor,
            reason: $reason,
            timestamps: [
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ],
        );
        $this->restockPaidOrderExactlyOnce($subOrder, $actor, 'seller_rejected');
        $this->setStatus(
            $subOrder,
            SubOrderStatus::RefundPending,
            $actor,
            reason: 'seller_rejected',
        );
        $order->forceFill(['status' => OrderStatus::RefundPending])->save();
        $this->outbox->queueOrder(
            $order,
            'order.rejected',
            ['reason' => $reason],
            $subOrder,
            "sub-order:{$subOrder->id}:rejected",
        );
    }

    private function prepare(
        Order $order,
        SubOrder $subOrder,
        User $actor,
    ): void {
        $this->setStatus(
            $subOrder,
            SubOrderStatus::Preparing,
            $actor,
            timestamps: ['preparing_at' => now()],
        );
        $order->forceFill(['status' => OrderStatus::Processing])->save();
        $this->outbox->queueOrder(
            $order,
            'order.preparing',
            subOrder: $subOrder,
            deduplicationKey: "sub-order:{$subOrder->id}:preparing",
        );
    }

    private function readyToShip(
        Order $order,
        SubOrder $subOrder,
        User $actor,
    ): void {
        $this->setStatus(
            $subOrder,
            SubOrderStatus::ReadyToShip,
            $actor,
            timestamps: ['ready_to_ship_at' => now()],
        );
        $order->forceFill(['status' => OrderStatus::Processing])->save();
        $this->outbox->queueOrder(
            $order,
            'order.ready_to_ship',
            subOrder: $subOrder,
            deduplicationKey: "sub-order:{$subOrder->id}:ready-to-ship",
        );
    }

    private function ship(
        Order $order,
        SubOrder $subOrder,
        User $actor,
        string $carrier,
        string $trackingCode,
    ): void {
        if ($carrier === '' || $trackingCode === '') {
            throw new ApiDomainException(
                'fulfillment.tracking_required',
                'نام حامل و کد رهگیری برای ارسال الزامی است.',
                422,
            );
        }

        $shipment = Shipment::query()->updateOrCreate(
            ['sub_order_id' => $subOrder->id],
            [
                'carrier' => $carrier,
                'tracking_code' => $trackingCode,
                'status' => 'shipped',
                'shipped_at' => now(),
                'delivered_at' => null,
            ],
        );
        $this->setStatus(
            $subOrder,
            SubOrderStatus::Shipped,
            $actor,
            metadata: [
                'shipment_id' => $shipment->id,
                'carrier' => $carrier,
                'tracking_code' => $trackingCode,
            ],
            timestamps: ['shipped_at' => now()],
        );
        $order->forceFill(['status' => OrderStatus::Shipped])->save();
        $this->outbox->queueOrder(
            $order,
            'order.shipped',
            [
                'carrier' => $carrier,
                'tracking_code' => $trackingCode,
            ],
            $subOrder,
            "sub-order:{$subOrder->id}:shipped",
        );
    }

    private function deliver(
        Order $order,
        SubOrder $subOrder,
        User $actor,
    ): void {
        $shipment = Shipment::query()
            ->where('sub_order_id', $subOrder->id)
            ->lockForUpdate()
            ->first();
        if (! $shipment) {
            throw new ApiDomainException(
                'fulfillment.shipment_missing',
                'اطلاعات ارسال برای این سفارش ثبت نشده است.',
                409,
            );
        }

        $shipment->forceFill([
            'status' => 'delivered',
            'delivered_at' => now(),
        ])->save();
        $this->setStatus(
            $subOrder,
            SubOrderStatus::Delivered,
            $actor,
            metadata: ['shipment_id' => $shipment->id],
            timestamps: ['delivered_at' => now()],
        );
        $order->forceFill(['status' => OrderStatus::Delivered])->save();
        $this->outbox->queueOrder(
            $order,
            'order.delivered',
            subOrder: $subOrder,
            deduplicationKey: "sub-order:{$subOrder->id}:delivered",
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $timestamps
     */
    private function setStatus(
        SubOrder $subOrder,
        SubOrderStatus $target,
        User $actor,
        ?string $reason = null,
        array $metadata = [],
        array $timestamps = [],
    ): void {
        $from = $subOrder->status;
        $subOrder->forceFill([
            'status' => $target,
            ...$timestamps,
        ])->save();
        SubOrderStatusHistory::query()->create([
            'sub_order_id' => $subOrder->id,
            'actor_id' => $actor->id,
            'from_status' => $from->value,
            'to_status' => $target->value,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function restockPaidOrderExactlyOnce(
        SubOrder $subOrder,
        User $actor,
        string $reason,
    ): void {
        $items = OrderItem::query()
            ->where('sub_order_id', $subOrder->id)
            ->orderBy('variant_id')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            if (! $item->variant_id) {
                throw new ApiDomainException(
                    'fulfillment.restock_reconciliation_required',
                    'Variant سفارش برای بازگردانی موجودی در دسترس نیست.',
                    409,
                );
            }

            $existing = InventoryRestock::query()
                ->where('order_item_id', $item->id)
                ->where('reason', $reason)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                continue;
            }

            $variant = ProductVariant::query()
                ->whereKey($item->variant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $balanceAfter = $variant->stock_on_hand + $item->quantity;
            $variant->forceFill(['stock_on_hand' => $balanceAfter])->save();
            $ledger = StockLedgerEntry::query()->create([
                'variant_id' => $variant->id,
                'roast_batch_id' => $item->roast_batch_id,
                'actor_id' => $actor->id,
                'delta' => $item->quantity,
                'balance_after' => $balanceAfter,
                'reason' => StockReason::Return,
                'reference_type' => 'sub_order',
                'reference_id' => $subOrder->id,
                'idempotency_key' => "fulfillment:restock:{$reason}:{$item->id}",
                'metadata' => [
                    'order_id' => $subOrder->order_id,
                    'sub_order_id' => $subOrder->id,
                    'order_item_id' => $item->id,
                    'reason' => $reason,
                ],
            ]);
            InventoryRestock::query()->create([
                'order_item_id' => $item->id,
                'variant_id' => $variant->id,
                'roast_batch_id' => $item->roast_batch_id,
                'actor_id' => $actor->id,
                'quantity' => $item->quantity,
                'reason' => $reason,
                'ledger_entry_id' => $ledger->id,
                'restocked_at' => now(),
            ]);
        }
    }
}
