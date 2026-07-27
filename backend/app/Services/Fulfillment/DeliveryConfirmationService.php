<?php

namespace App\Services\Fulfillment;

use App\Enums\DeliveryConfirmationSource;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLegStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\ApiDomainException;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Shipment;
use App\Models\ShipmentDeliveryConfirmation;
use App\Models\ShipmentLeg;
use App\Models\SubOrder;
use App\Models\SubOrderStatusHistory;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Notifications\NotificationOutboxService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeliveryConfirmationService
{
    public function __construct(
        private readonly NotificationOutboxService $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /** @param array{idempotency_key: string, proof_type: string, proof_payload?: array<mixed>|null, customer_note?: string|null} $input */
    public function confirm(
        ?User $actor,
        ShipmentLeg $leg,
        DeliveryConfirmationSource $source,
        array $input,
        ?Request $request = null,
    ): Order {
        return DB::transaction(function () use ($actor, $leg, $source, $input, $request): Order {
            $lockedLeg = ShipmentLeg::query()->whereKey($leg->id)->lockForUpdate()->firstOrFail();
            $subOrder = SubOrder::query()->whereKey($lockedLeg->sub_order_id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($lockedLeg->order_id)->lockForUpdate()->firstOrFail();

            $this->confirmLocked($actor, $order, $subOrder, $lockedLeg, $source, $input, $request);
            $this->syncOrderStatus($order);

            return $this->loadOrder($order->refresh());
        }, 3);
    }

    /** @param array{idempotency_key: string, proof_type: string, proof_payload?: array<mixed>|null, customer_note?: string|null} $input */
    public function confirmLocked(
        ?User $actor,
        Order $order,
        SubOrder $subOrder,
        ShipmentLeg $leg,
        DeliveryConfirmationSource $source,
        array $input,
        ?Request $request = null,
    ): ShipmentDeliveryConfirmation {
        $idempotencyKey = trim($input['idempotency_key']);
        $existing = ShipmentDeliveryConfirmation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
        if ($existing instanceof ShipmentDeliveryConfirmation) {
            if ($existing->shipment_leg_id !== $leg->id) {
                throw new ApiDomainException(
                    'delivery.idempotency_conflict',
                    'کلید تکرارپذیری قبلاً برای ارسال دیگری استفاده شده است.',
                    409,
                );
            }

            return $existing;
        }

        if ($source === DeliveryConfirmationSource::Customer && ! $this->isFinalLeg($leg)) {
            throw new ApiDomainException(
                'delivery.customer_final_leg_only',
                'مشتری فقط دریافت مرحله نهایی ارسال را تأیید می‌کند.',
                409,
            );
        }

        if (! in_array($leg->status, [
            ShipmentLegStatus::PickedUp,
            ShipmentLegStatus::InTransit,
            ShipmentLegStatus::Delivered,
        ], true)) {
            throw new ApiDomainException(
                'delivery.leg_not_deliverable',
                'این مرحله ارسال هنوز قابل تأیید تحویل نیست.',
                409,
            );
        }

        $legConfirmation = ShipmentDeliveryConfirmation::query()
            ->where('shipment_leg_id', $leg->id)
            ->lockForUpdate()
            ->first();
        if ($legConfirmation instanceof ShipmentDeliveryConfirmation) {
            throw new ApiDomainException(
                'delivery.leg_already_confirmed',
                'تحویل این مرحله قبلاً ثبت شده است.',
                409,
            );
        }

        $confirmedAt = now();
        $confirmation = ShipmentDeliveryConfirmation::query()->create([
            'shipment_leg_id' => $leg->id,
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'confirmed_by_id' => $actor?->id,
            'source' => $source,
            'idempotency_key' => $idempotencyKey,
            'proof_type' => trim($input['proof_type']),
            'proof_payload' => $input['proof_payload'] ?? null,
            'customer_note' => isset($input['customer_note'])
                ? trim((string) $input['customer_note']) ?: null
                : null,
            'confirmed_at' => $confirmedAt,
        ]);

        $previousLegStatus = $leg->status->value;
        $leg->forceFill([
            'status' => ShipmentLegStatus::Delivered,
            'delivered_at' => $confirmedAt,
        ])->save();

        $finalLeg = $this->isFinalLeg($leg);
        if ($finalLeg) {
            $this->completeSubOrder($actor, $order, $subOrder, $leg, $confirmation, $confirmedAt);
        } else {
            $nextLeg = ShipmentLeg::query()
                ->where('sub_order_id', $subOrder->id)
                ->where('sequence', '>', $leg->sequence)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->first();
            if ($nextLeg instanceof ShipmentLeg && $nextLeg->status === ShipmentLegStatus::Planned) {
                $nextLeg->forceFill(['status' => ShipmentLegStatus::AwaitingPickup])->save();
            }

            OrderEvent::query()->create([
                'order_id' => $order->id,
                'sub_order_id' => $subOrder->id,
                'shipment_leg_id' => $leg->id,
                'event_type' => 'shipment.leg_delivered',
                'previous_state' => $previousLegStatus,
                'next_state' => ShipmentLegStatus::Delivered->value,
                'actor_type' => $source->value,
                'actor_user_id' => $actor?->id,
                'request_id' => $request?->attributes->get('request_id'),
                'reason_code' => 'intermediate_leg_delivered',
                'customer_title' => 'بسته به مرحله بعدی مسیر رسید',
                'customer_description' => 'ارسال هنوز در مسیر است و تحویل نهایی به مشتری ثبت نشده است.',
                'internal_metadata' => [
                    'confirmation_id' => $confirmation->id,
                    'route_type' => $leg->route_type,
                    'sequence' => $leg->sequence,
                ],
                'occurred_at' => $confirmedAt,
                'created_at' => $confirmedAt,
            ]);
        }

        $this->audit->record(
            'delivery.confirmed',
            actor: $actor,
            auditable: $confirmation,
            metadata: [
                'order_id' => $order->id,
                'sub_order_id' => $subOrder->id,
                'shipment_leg_id' => $leg->id,
                'source' => $source->value,
                'proof_type' => $confirmation->proof_type,
                'is_final_leg' => $finalLeg,
            ],
            request: $request,
        );

        return $confirmation;
    }

    private function completeSubOrder(
        ?User $actor,
        Order $order,
        SubOrder $subOrder,
        ShipmentLeg $leg,
        ShipmentDeliveryConfirmation $confirmation,
        CarbonInterface $confirmedAt,
    ): void {
        $from = $subOrder->status;
        if (! in_array($from, [SubOrderStatus::Shipped, SubOrderStatus::Delivered], true)) {
            throw new ApiDomainException(
                'delivery.sub_order_not_shipped',
                'زیرسفارش پیش از تحویل نهایی باید به حمل تحویل شده باشد.',
                409,
            );
        }

        $disputeEndsAt = $confirmedAt->copy()->addHours(
            (int) config('rosta.settlement.dispute_window_hours', 72),
        );
        $subOrder->forceFill([
            'status' => SubOrderStatus::Delivered,
            'delivered_at' => $confirmedAt,
            'delivery_confirmed_at' => $confirmedAt,
            'dispute_window_ends_at' => $subOrder->dispute_window_ends_at ?? $disputeEndsAt,
        ])->save();

        if ($from !== SubOrderStatus::Delivered) {
            SubOrderStatusHistory::query()->create([
                'sub_order_id' => $subOrder->id,
                'actor_id' => $actor?->id,
                'from_status' => $from->value,
                'to_status' => SubOrderStatus::Delivered->value,
                'reason' => 'final_shipment_leg_delivery_confirmed',
                'metadata' => [
                    'shipment_leg_id' => $leg->id,
                    'confirmation_id' => $confirmation->id,
                    'dispute_window_ends_at' => $subOrder->dispute_window_ends_at?->toIso8601String(),
                ],
                'created_at' => $confirmedAt,
            ]);
        }

        Shipment::query()
            ->where('sub_order_id', $subOrder->id)
            ->lockForUpdate()
            ->update([
                'status' => 'delivered',
                'delivered_at' => $confirmedAt,
            ]);

        OrderEvent::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'shipment_leg_id' => $leg->id,
            'event_type' => 'delivery.final_confirmed',
            'previous_state' => $from->value,
            'next_state' => SubOrderStatus::Delivered->value,
            'actor_type' => $confirmation->source->value,
            'actor_user_id' => $actor?->id,
            'reason_code' => 'final_leg_delivered',
            'customer_title' => 'سفارش تحویل شد',
            'customer_description' => 'مهلت اعلام مشکل از زمان ثبت تحویل آغاز شده است.',
            'internal_metadata' => [
                'confirmation_id' => $confirmation->id,
                'proof_type' => $confirmation->proof_type,
                'dispute_window_ends_at' => $subOrder->dispute_window_ends_at?->toIso8601String(),
            ],
            'occurred_at' => $confirmedAt,
            'created_at' => $confirmedAt,
        ]);

        $this->outbox->queueOrder(
            $order,
            'order.delivered',
            [
                'dispute_window_ends_at' => $subOrder->dispute_window_ends_at?->toIso8601String(),
            ],
            $subOrder,
            "sub-order:{$subOrder->id}:delivery-confirmed",
        );
    }

    private function isFinalLeg(ShipmentLeg $leg): bool
    {
        return ! ShipmentLeg::query()
            ->where('sub_order_id', $leg->sub_order_id)
            ->where('sequence', '>', $leg->sequence)
            ->exists();
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
