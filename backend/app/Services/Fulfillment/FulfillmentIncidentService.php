<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentIncidentResolution;
use App\Enums\FulfillmentIncidentStatus;
use App\Enums\FulfillmentSlaStatus;
use App\Enums\OrderStatus;
use App\Enums\SettlementAllocationStatus;
use App\Enums\StockReason;
use App\Enums\SubOrderStatus;
use App\Exceptions\ApiDomainException;
use App\Models\FulfillmentIncident;
use App\Models\InventoryRestock;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\SettlementAllocation;
use App\Models\StockLedgerEntry;
use App\Models\SubOrder;
use App\Models\SubOrderStatusHistory;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Refunds\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FulfillmentIncidentService
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly AuditRecorder $audit,
    ) {}

    /** @param array{code: string, description: string, severity?: string} $input */
    public function report(
        User $actor,
        Roastery $roastery,
        string $orderId,
        array $input,
        Request $request,
    ): Order {
        return DB::transaction(function () use ($actor, $roastery, $orderId, $input, $request): Order {
            $subOrder = SubOrder::query()
                ->where('order_id', $orderId)
                ->where('roastery_id', $roastery->id)
                ->lockForUpdate()
                ->firstOrFail();
            $order = Order::query()->whereKey($subOrder->order_id)->lockForUpdate()->firstOrFail();

            if (! in_array($subOrder->status, [
                SubOrderStatus::Accepted,
                SubOrderStatus::Preparing,
                SubOrderStatus::ReadyToShip,
            ], true)) {
                throw new ApiDomainException(
                    'fulfillment.incident_invalid_state',
                    'در این مرحله امکان ثبت مشکل تأمین وجود ندارد.',
                    409,
                );
            }

            $existing = FulfillmentIncident::query()
                ->where('sub_order_id', $subOrder->id)
                ->where('status', FulfillmentIncidentStatus::Open->value)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof FulfillmentIncident) {
                throw new ApiDomainException(
                    'fulfillment.incident_already_open',
                    'برای این زیرسفارش یک Incident باز وجود دارد.',
                    409,
                );
            }

            $reportedAt = now();
            $eventPreviousState = $subOrder->status->value;
            $incident = FulfillmentIncident::query()->create([
                'order_id' => $order->id,
                'sub_order_id' => $subOrder->id,
                'roastery_id' => $roastery->id,
                'reported_by_id' => $actor->id,
                'status' => FulfillmentIncidentStatus::Open,
                'code' => $input['code'],
                'severity' => $input['severity'] ?? 'high',
                'description' => trim($input['description']),
                'reported_at' => $reportedAt,
            ]);

            $subOrder->forceFill([
                'sla_status' => FulfillmentSlaStatus::ExceptionOpen,
                'incident_opened_at' => $reportedAt,
                'incident_resolved_at' => null,
            ])->save();

            OrderEvent::query()->create([
                'order_id' => $order->id,
                'sub_order_id' => $subOrder->id,
                'event_type' => 'fulfillment.incident_reported',
                'previous_state' => $eventPreviousState,
                'next_state' => $subOrder->status->value,
                'actor_type' => 'roastery',
                'actor_user_id' => $actor->id,
                'request_id' => $request->attributes->get('request_id'),
                'reason_code' => $incident->code,
                'customer_title' => 'بررسی عملیاتی سفارش آغاز شد',
                'customer_description' => 'تیم رستا در حال بررسی یک مشکل استثنایی در آماده‌سازی این بخش از سفارش است.',
                'internal_metadata' => [
                    'incident_id' => $incident->id,
                    'severity' => $incident->severity,
                    'description' => $incident->description,
                ],
                'occurred_at' => $reportedAt,
                'created_at' => $reportedAt,
            ]);

            $this->audit->record(
                'fulfillment.incident_reported',
                actor: $actor,
                auditable: $incident,
                metadata: [
                    'order_id' => $order->id,
                    'sub_order_id' => $subOrder->id,
                    'roastery_id' => $roastery->id,
                    'code' => $incident->code,
                    'severity' => $incident->severity,
                ],
                request: $request,
            );

            return $this->loadOrder($order);
        }, 3);
    }

    /** @param array{resolution: string, note: string, extend_sla_hours?: int|null} $input */
    public function resolve(
        User $actor,
        string $incidentId,
        array $input,
        Request $request,
    ): Order {
        return DB::transaction(function () use ($actor, $incidentId, $input, $request): Order {
            $incident = FulfillmentIncident::query()->whereKey($incidentId)->lockForUpdate()->firstOrFail();
            if ($incident->status !== FulfillmentIncidentStatus::Open) {
                throw new ApiDomainException(
                    'fulfillment.incident_already_resolved',
                    'این Incident قبلاً تعیین تکلیف شده است.',
                    409,
                );
            }

            $subOrder = SubOrder::query()->whereKey($incident->sub_order_id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($incident->order_id)->lockForUpdate()->firstOrFail();
            $resolution = FulfillmentIncidentResolution::from($input['resolution']);
            $resolvedAt = now();
            $eventPreviousState = $subOrder->status->value;
            $refund = null;

            if ($resolution === FulfillmentIncidentResolution::ResumeFulfillment) {
                $extension = (int) ($input['extend_sla_hours'] ?? 0);
                $subOrder->forceFill([
                    'preparation_due_at' => ($subOrder->preparation_due_at ?? $resolvedAt)->addHours($extension),
                    'handoff_due_at' => ($subOrder->handoff_due_at ?? $resolvedAt)->addHours($extension),
                    'sla_status' => FulfillmentSlaStatus::ExceptionResolved,
                    'incident_resolved_at' => $resolvedAt,
                ])->save();
            } else {
                if (! in_array($subOrder->status, [
                    SubOrderStatus::Accepted,
                    SubOrderStatus::Preparing,
                    SubOrderStatus::ReadyToShip,
                ], true)) {
                    throw new ApiDomainException(
                        'fulfillment.exception_cancellation_invalid_state',
                        'لغو استثنایی پس از تحویل به حمل مجاز نیست.',
                        409,
                    );
                }

                $this->restockExactlyOnce($subOrder, $actor, 'admin_exception_cancelled');
                $this->reverseAllocations($subOrder, $incident);
                $from = $subOrder->status;
                $subOrder->forceFill([
                    'status' => SubOrderStatus::RefundPending,
                    'cancelled_at' => $resolvedAt,
                    'cancellation_code' => 'admin_exception_cancelled',
                    'sla_status' => FulfillmentSlaStatus::ExceptionResolved,
                    'incident_resolved_at' => $resolvedAt,
                ])->save();
                SubOrderStatusHistory::query()->create([
                    'sub_order_id' => $subOrder->id,
                    'actor_id' => $actor->id,
                    'from_status' => $from->value,
                    'to_status' => SubOrderStatus::RefundPending->value,
                    'reason' => 'admin_exception_cancelled',
                    'metadata' => ['incident_id' => $incident->id],
                    'created_at' => $resolvedAt,
                ]);

                $refund = $this->refunds->request($actor, $order, [
                    'amount' => $subOrder->grand_total,
                    'reason' => 'لغو استثنایی زیرسفارش '.$subOrder->id.': '.trim($input['note']),
                    'idempotency_key' => 'incident:'.$incident->id.':sub-order-refund',
                ], $request);

                $remaining = SubOrder::query()
                    ->where('order_id', $order->id)
                    ->where('id', '!=', $subOrder->id)
                    ->whereNotIn('status', [
                        SubOrderStatus::Cancelled->value,
                        SubOrderStatus::RefundPending->value,
                        SubOrderStatus::Refunded->value,
                    ])
                    ->exists();
                $order->forceFill([
                    'status' => $remaining
                        ? OrderStatus::PartiallyCancelled
                        : OrderStatus::RefundPending,
                ])->save();
            }

            $incident->forceFill([
                'status' => FulfillmentIncidentStatus::Resolved,
                'resolved_by_id' => $actor->id,
                'refund_attempt_id' => $refund?->id,
                'resolution' => $resolution,
                'resolution_note' => trim($input['note']),
                'resolved_at' => $resolvedAt,
            ])->save();

            OrderEvent::query()->create([
                'order_id' => $order->id,
                'sub_order_id' => $subOrder->id,
                'event_type' => $resolution === FulfillmentIncidentResolution::CancelAndRefund
                    ? 'fulfillment.exception_cancelled'
                    : 'fulfillment.incident_resolved',
                'previous_state' => $eventPreviousState,
                'next_state' => $subOrder->status->value,
                'actor_type' => 'administrator',
                'actor_user_id' => $actor->id,
                'request_id' => $request->attributes->get('request_id'),
                'reason_code' => $resolution->value,
                'customer_title' => $resolution === FulfillmentIncidentResolution::CancelAndRefund
                    ? 'بازپرداخت این بخش از سفارش ثبت شد'
                    : 'آماده‌سازی سفارش ادامه پیدا می‌کند',
                'customer_description' => $resolution === FulfillmentIncidentResolution::CancelAndRefund
                    ? 'فقط مبلغ همین بخش از سفارش برای بازپرداخت ثبت شده و سایر بخش‌ها ادامه دارند.'
                    : 'مشکل عملیاتی رفع شده و روستری آماده‌سازی را ادامه می‌دهد.',
                'internal_metadata' => [
                    'incident_id' => $incident->id,
                    'refund_attempt_id' => $refund?->id,
                    'resolution_note' => $incident->resolution_note,
                ],
                'occurred_at' => $resolvedAt,
                'created_at' => $resolvedAt,
            ]);

            $this->audit->record(
                'fulfillment.incident_resolved',
                actor: $actor,
                auditable: $incident,
                metadata: [
                    'order_id' => $order->id,
                    'sub_order_id' => $subOrder->id,
                    'resolution' => $resolution->value,
                    'refund_attempt_id' => $refund?->id,
                ],
                request: $request,
            );

            return $this->loadOrder($order->refresh());
        }, 3);
    }

    private function restockExactlyOnce(SubOrder $subOrder, User $actor, string $reason): void
    {
        $items = OrderItem::query()
            ->where('sub_order_id', $subOrder->id)
            ->orderBy('variant_id')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            if ($item->variant_id === null) {
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
            if ($existing instanceof InventoryRestock) {
                continue;
            }

            $variant = ProductVariant::query()->whereKey($item->variant_id)->lockForUpdate()->firstOrFail();
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
                'idempotency_key' => 'r5h:restock:'.$reason.':'.$item->id,
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

    private function reverseAllocations(SubOrder $subOrder, FulfillmentIncident $incident): void
    {
        $allocations = SettlementAllocation::query()
            ->where('sub_order_id', $subOrder->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            if ($allocation->status === SettlementAllocationStatus::Reversed) {
                continue;
            }
            if ($allocation->status === SettlementAllocationStatus::Paid) {
                throw new ApiDomainException(
                    'fulfillment.paid_allocation_reconciliation_required',
                    'این زیرسفارش دارای تسویه پرداخت‌شده است و باید دستی تطبیق داده شود.',
                    409,
                );
            }
            $allocation->forceFill([
                'status' => SettlementAllocationStatus::Reversed,
                'reversed_at' => now(),
                'metadata' => [
                    ...($allocation->metadata ?? []),
                    'reversal_reason' => 'admin_exception_cancelled',
                    'incident_id' => $incident->id,
                ],
            ])->save();
        }
    }

    private function loadOrder(Order $order): Order
    {
        return $order->load([
            'subOrders.roastery.logo',
            'subOrders.roastery.cover',
            'subOrders.items.services.grindingProfile',
            'subOrders.shipment',
            'subOrders.shipmentLegs',
            'subOrders.fulfillmentIncidents',
            'events',
        ]);
    }
}
