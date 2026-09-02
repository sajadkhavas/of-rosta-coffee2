<?php

namespace App\Services\Fulfillment;

use App\Enums\DeliveryConfirmationSource;
use App\Enums\FulfillmentIncidentStatus;
use App\Enums\FulfillmentSlaStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLegStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\ApiDomainException;
use App\Models\FulfillmentIncident;
use App\Models\Order;
use App\Models\OrderInternalNote;
use App\Models\Roastery;
use App\Models\Shipment;
use App\Models\ShipmentLeg;
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
        private readonly DeliveryConfirmationService $deliveries,
        private readonly FreshnessDispatchGuard $freshness,
    ) {}

    /**
     * @param array{
     *   status: string,
     *   carrier?: string|null,
     *   tracking_code?: string|null,
     *   internal_note?: string|null
     * } $input
     */
    public function transition(
        User $actor,
        Roastery $roastery,
        string $orderId,
        array $input,
        Request $request,
        bool $allowDelivery = false,
    ): Order {
        $target = SubOrderStatus::from($input['status']);

        return DB::transaction(function () use (
            $actor,
            $roastery,
            $orderId,
            $input,
            $target,
            $request,
            $allowDelivery,
        ): Order {
            $subOrder = SubOrder::query()
                ->where('order_id', $orderId)
                ->where('roastery_id', $roastery->id)
                ->lockForUpdate()
                ->firstOrFail();
            $order = Order::query()->whereKey($subOrder->order_id)->lockForUpdate()->firstOrFail();

            $this->assertNoOpenIncident($subOrder);
            $from = $subOrder->status;
            $this->assertTransitionAllowed($order, $from, $target, $allowDelivery);

            if ($target === SubOrderStatus::Shipped) {
                $this->freshness->assertDispatchable($subOrder);
            }

            match ($target) {
                SubOrderStatus::Preparing => $this->prepare($order, $subOrder, $actor),
                SubOrderStatus::ReadyToShip => $this->readyToShip($order, $subOrder, $actor),
                SubOrderStatus::Shipped => $this->ship(
                    $order,
                    $subOrder,
                    $actor,
                    trim((string) ($input['carrier'] ?? '')),
                    trim((string) ($input['tracking_code'] ?? '')),
                ),
                SubOrderStatus::Delivered => $this->deliver($order, $subOrder, $actor, $request),
                default => throw new ApiDomainException(
                    'fulfillment.transition_unsupported',
                    'روستری فقط می‌تواند مراحل آماده‌سازی و تحویل به حمل را ثبت کند.',
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

            $this->syncOrderStatus($order);
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
                    'contractual_acceptance' => true,
                ],
                request: $request,
            );

            return $this->loadOrder($order->refresh());
        }, 3);
    }

    private function assertNoOpenIncident(SubOrder $subOrder): void
    {
        $open = FulfillmentIncident::query()
            ->where('sub_order_id', $subOrder->id)
            ->where('status', FulfillmentIncidentStatus::Open->value)
            ->lockForUpdate()
            ->exists();
        if ($open) {
            throw new ApiDomainException(
                'fulfillment.incident_resolution_required',
                'تا تعیین تکلیف Incident باز، تغییر مرحله سفارش مجاز نیست.',
                409,
            );
        }
    }

    private function assertTransitionAllowed(
        Order $order,
        SubOrderStatus $from,
        SubOrderStatus $to,
        bool $allowDelivery,
    ): void {
        $allowed = match ($from) {
            SubOrderStatus::Accepted => [SubOrderStatus::Preparing],
            SubOrderStatus::Preparing => [SubOrderStatus::ReadyToShip],
            SubOrderStatus::ReadyToShip => [SubOrderStatus::Shipped],
            SubOrderStatus::Shipped => $allowDelivery ? [SubOrderStatus::Delivered] : [],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new ApiDomainException(
                'fulfillment.transition_invalid',
                "تغییر وضعیت از {$from->value} به {$to->value} مجاز نیست.",
                409,
            );
        }

        if (! in_array($order->status, [
            OrderStatus::Paid,
            OrderStatus::Processing,
            OrderStatus::PartiallyShipped,
            OrderStatus::Shipped,
            OrderStatus::PartiallyDelivered,
        ], true)) {
            throw new ApiDomainException(
                'fulfillment.order_not_paid',
                'عملیات روستری فقط برای سفارش پرداخت‌شده مجاز است.',
                409,
            );
        }
    }

    private function prepare(Order $order, SubOrder $subOrder, User $actor): void
    {
        $this->setStatus(
            $subOrder,
            SubOrderStatus::Preparing,
            $actor,
            timestamps: [
                'preparing_at' => now(),
                'sla_status' => FulfillmentSlaStatus::Preparing,
            ],
        );
        $this->outbox->queueOrder(
            $order,
            'order.preparing',
            subOrder: $subOrder,
            deduplicationKey: "sub-order:{$subOrder->id}:preparing",
        );
    }

    private function readyToShip(Order $order, SubOrder $subOrder, User $actor): void
    {
        $this->setStatus(
            $subOrder,
            SubOrderStatus::ReadyToShip,
            $actor,
            timestamps: [
                'ready_to_ship_at' => now(),
                'sla_status' => FulfillmentSlaStatus::ReadyToShip,
            ],
        );

        ShipmentLeg::query()
            ->where('sub_order_id', $subOrder->id)
            ->where('status', ShipmentLegStatus::Planned->value)
            ->orderBy('sequence')
            ->limit(1)
            ->update(['status' => ShipmentLegStatus::AwaitingPickup->value]);

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
                'نام حامل و کد رهگیری برای تحویل به حمل الزامی است.',
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

        $firstLeg = ShipmentLeg::query()
            ->where('sub_order_id', $subOrder->id)
            ->orderBy('sequence')
            ->lockForUpdate()
            ->first();
        if (! $firstLeg instanceof ShipmentLeg) {
            $firstLeg = ShipmentLeg::query()->create([
                'order_id' => $order->id,
                'sub_order_id' => $subOrder->id,
                'route_type' => 'roastery_to_customer',
                'sequence' => 1,
                'status' => ShipmentLegStatus::AwaitingPickup,
                'charge_owner_type' => 'roastery',
                'charge_owner_id' => $subOrder->roastery_id,
                'gross_amount' => $subOrder->shipping_total,
                'tax_amount' => 0,
                'total_amount' => $subOrder->shipping_total,
                'currency' => $subOrder->currency,
                'origin_snapshot' => [
                    'type' => 'roastery',
                    'id' => $subOrder->roastery_id,
                ],
                'destination_snapshot' => $order->address_snapshot,
                'planned_at' => now(),
            ]);
        }
        $firstLeg->forceFill([
            'legacy_shipment_id' => $shipment->id,
            'status' => ShipmentLegStatus::PickedUp,
            'carrier' => $carrier,
            'tracking_code' => $trackingCode,
            'picked_up_at' => now(),
        ])->save();

        $this->setStatus(
            $subOrder,
            SubOrderStatus::Shipped,
            $actor,
            metadata: [
                'shipment_id' => $shipment->id,
                'shipment_leg_id' => $firstLeg->id,
                'carrier' => $carrier,
                'tracking_code' => $trackingCode,
            ],
            timestamps: [
                'shipped_at' => now(),
                'sla_status' => FulfillmentSlaStatus::HandedOff,
            ],
        );
        $this->outbox->queueOrder(
            $order,
            'order.shipped',
            ['carrier' => $carrier, 'tracking_code' => $trackingCode],
            $subOrder,
            "sub-order:{$subOrder->id}:shipped",
        );
    }

    private function deliver(
        Order $order,
        SubOrder $subOrder,
        User $actor,
        Request $request,
    ): void {
        $finalLeg = ShipmentLeg::query()
            ->where('sub_order_id', $subOrder->id)
            ->orderByDesc('sequence')
            ->lockForUpdate()
            ->first();
        if (! $finalLeg instanceof ShipmentLeg) {
            throw new ApiDomainException(
                'fulfillment.shipment_leg_missing',
                'مرحله ارسال برای این سفارش ثبت نشده است.',
                409,
            );
        }

        $this->deliveries->confirmLocked(
            $actor,
            $order,
            $subOrder,
            $finalLeg,
            DeliveryConfirmationSource::Administrator,
            [
                'idempotency_key' => "legacy-admin-delivery:{$subOrder->id}:{$finalLeg->id}",
                'proof_type' => 'administrator_override',
                'proof_payload' => ['legacy_fulfillment_transition' => true],
            ],
            $request,
        );
    }

    /** @param array<string, mixed> $metadata @param array<string, mixed> $timestamps */
    private function setStatus(
        SubOrder $subOrder,
        SubOrderStatus $target,
        User $actor,
        ?string $reason = null,
        array $metadata = [],
        array $timestamps = [],
    ): void {
        $from = $subOrder->status;
        $subOrder->forceFill(['status' => $target, ...$timestamps])->save();
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

    private function syncOrderStatus(Order $order): void
    {
        $statuses = SubOrder::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->pluck('status')
            ->map(static fn (SubOrderStatus|string $status): string => $status instanceof SubOrderStatus
                ? $status->value
                : (string) $status);

        $total = $statuses->count();
        $delivered = $statuses->filter(static fn (string $status): bool => $status === SubOrderStatus::Delivered->value)->count();
        $shipped = $statuses->filter(static fn (string $status): bool => in_array($status, [
            SubOrderStatus::Shipped->value,
            SubOrderStatus::Delivered->value,
        ], true))->count();
        $cancelled = $statuses->filter(static fn (string $status): bool => in_array($status, [
            SubOrderStatus::Cancelled->value,
            SubOrderStatus::RefundPending->value,
            SubOrderStatus::Refunded->value,
        ], true))->count();

        $status = match (true) {
            $total > 0 && $delivered === $total => OrderStatus::Delivered,
            $delivered > 0 => OrderStatus::PartiallyDelivered,
            $total > 0 && $shipped === $total => OrderStatus::Shipped,
            $shipped > 0 => OrderStatus::PartiallyShipped,
            $cancelled > 0 && $cancelled < $total => OrderStatus::PartiallyCancelled,
            default => OrderStatus::Processing,
        };
        $order->forceFill(['status' => $status])->save();
    }

    private function loadOrder(Order $order): Order
    {
        return $order->load([
            'subOrders.roastery.logo',
            'subOrders.roastery.cover',
            'subOrders.items.services.grindingProfile',
            'subOrders.shipment',
            'subOrders.shipmentLegs.deliveryConfirmation',
            'subOrders.fulfillmentIncidents',
            'events',
        ]);
    }
}
