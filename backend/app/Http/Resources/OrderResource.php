<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\OrderItemService;
use App\Models\ShipmentLeg;
use App\Models\SubOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $publicOrderStatus = $this->status === OrderStatus::RefundPending
            ? OrderStatus::Processing->value
            : $this->status->value;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $publicOrderStatus,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'grand_total' => $this->grand_total,
            'currency' => $this->currency,
            'sub_orders' => $this->subOrders
                ->map(fn (SubOrder $subOrder): array => $this->subOrderPayload($subOrder))
                ->values()
                ->all(),
            'events' => $this->events
                ->map(static fn (OrderEvent $event): array => [
                    'id' => $event->id,
                    'sub_order_id' => $event->sub_order_id,
                    'type' => $event->event_type,
                    'previous_state' => $event->previous_state,
                    'next_state' => $event->next_state,
                    'title' => $event->customer_title,
                    'description' => $event->customer_description,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                ])
                ->values()
                ->all(),
            'address' => $this->address_snapshot,
            'subtotal' => $this->subtotal,
            'packaging_total' => $this->subOrders->sum('packaging_total'),
            'grinding_total' => $this->subOrders->sum('grinding_total'),
            'shipping_total' => $this->shipping_total,
            'discount_total' => $this->discount_total,
        ];
    }

    /** @return array<string, mixed> */
    private function subOrderPayload(SubOrder $subOrder): array
    {
        $legacyShipment = $subOrder->shipment;

        return [
            'id' => $subOrder->id,
            'status' => $subOrder->status->value,
            'acceptance_status' => $subOrder->acceptance_status->value,
            'customer_cancellable' => $subOrder->isCustomerCancellable(),
            'roastery' => [
                'id' => $subOrder->roastery->id,
                'name' => $subOrder->roastery->name,
                'slug' => $subOrder->roastery->slug,
            ],
            'items' => $subOrder->items
                ->map(fn (OrderItem $item): array => $this->itemPayload($item))
                ->values()
                ->all(),
            'subtotal' => $subOrder->subtotal,
            'packaging_total' => $subOrder->packaging_total,
            'grinding_total' => $subOrder->grinding_total,
            'shipping_total' => $subOrder->shipping_total,
            'discount_total' => $subOrder->discount_total,
            'tax_total' => $subOrder->tax_total,
            'grand_total' => $subOrder->grand_total,
            'currency' => $subOrder->currency,
            'shipment' => $legacyShipment === null
                ? null
                : [
                    'id' => $legacyShipment->id,
                    'carrier' => $legacyShipment->carrier,
                    'tracking_code' => $legacyShipment->tracking_code,
                    'status' => $legacyShipment->status,
                    'shipped_at' => $legacyShipment->shipped_at?->toIso8601String(),
                    'delivered_at' => $legacyShipment->delivered_at?->toIso8601String(),
                ],
            'shipment_legs' => $subOrder->shipmentLegs
                ->map(static fn (ShipmentLeg $leg): array => [
                    'id' => $leg->id,
                    'route_type' => $leg->route_type,
                    'sequence' => $leg->sequence,
                    'status' => $leg->status->value,
                    'carrier' => $leg->carrier,
                    'tracking_code' => $leg->tracking_code,
                    'total_amount' => $leg->total_amount,
                    'currency' => $leg->currency,
                    'planned_at' => $leg->planned_at?->toIso8601String(),
                    'picked_up_at' => $leg->picked_up_at?->toIso8601String(),
                    'delivered_at' => $leg->delivered_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function itemPayload(OrderItem $item): array
    {
        $product = $item->product_snapshot;
        $variant = $item->variant_snapshot;

        return [
            'id' => $item->id,
            'product' => [
                'id' => $product['id'],
                'name' => $product['name'],
                'slug' => $product['slug'],
                'primary_image' => $product['primary_image'] ?? null,
            ],
            'variant' => [
                'id' => $variant['id'],
                'sku' => $variant['sku'],
                'weight_grams' => $variant['weight_grams'],
                'price' => $item->unit_price,
                'currency' => $variant['currency'] ?? 'IRR',
            ],
            'quantity' => $item->quantity,
            'line_total' => $item->line_total,
            'services' => $item->services
                ->map(static fn (OrderItemService $service): array => [
                    'id' => $service->id,
                    'type' => $service->service_type,
                    'provider_type' => $service->provider_type,
                    'status' => $service->status->value,
                    'grinding_profile' => $service->grindingProfile === null
                        ? null
                        : [
                            'id' => $service->grindingProfile->id,
                            'code' => $service->grindingProfile->code,
                            'version' => $service->grindingProfile->version,
                            'name' => $service->grindingProfile->public_name,
                            'brew_method' => $service->grindingProfile->brew_method,
                        ],
                    'service_fee' => $service->service_fee,
                    'packaging_fee' => $service->packaging_fee,
                    'shipping_fee' => $service->shipping_fee,
                    'tax_amount' => $service->tax_amount,
                    'total_amount' => $service->total_amount,
                    'currency' => $service->currency,
                    'is_free' => $service->total_amount === 0,
                    'label' => $service->service_snapshot['label'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }
}
