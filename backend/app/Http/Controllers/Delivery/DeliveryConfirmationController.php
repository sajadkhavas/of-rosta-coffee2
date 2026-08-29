<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\CarrierEventType;
use App\Enums\DeliveryConfirmationSource;
use App\Http\Requests\Delivery\CarrierDeliveryWebhookRequest;
use App\Http\Requests\Delivery\ConfirmDeliveryRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\ShipmentLeg;
use App\Models\User;
use App\Services\Carrier\CarrierOperationsService;
use App\Services\Catalog\CatalogAccess;
use App\Services\Fulfillment\DeliveryConfirmationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class DeliveryConfirmationController
{
    public function customer(
        ConfirmDeliveryRequest $request,
        string $orderId,
        string $shipmentLegId,
        DeliveryConfirmationService $deliveries,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        $order = Order::query()->whereKey($orderId)->where('user_id', $user->id)->firstOrFail();
        $leg = ShipmentLeg::query()
            ->whereKey($shipmentLegId)
            ->where('order_id', $order->id)
            ->firstOrFail();

        return new OrderResource($deliveries->confirm(
            $user,
            $leg,
            DeliveryConfirmationSource::Customer,
            $request->validated(),
            $request,
        ));
    }

    public function administrator(
        ConfirmDeliveryRequest $request,
        string $shipmentLegId,
        CatalogAccess $access,
        DeliveryConfirmationService $deliveries,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $leg = ShipmentLeg::query()->findOrFail($shipmentLegId);

        return new OrderResource($deliveries->confirm(
            $user,
            $leg,
            DeliveryConfirmationSource::Administrator,
            $request->validated(),
            $request,
        ));
    }

    public function carrier(
        CarrierDeliveryWebhookRequest $request,
        CarrierOperationsService $operations,
    ): JsonResponse {
        $validated = $request->validated();
        $proof = $validated['proof_payload'] ?? [];
        $eventId = (string) $request->attributes->get('carrier_event_id');
        $result = $operations->ingest([
            'carrier' => $validated['carrier'],
            'tracking_code' => $validated['tracking_code'],
            'event_type' => CarrierEventType::Delivered->value,
            'occurred_at' => isset($proof['occurred_at'])
                ? (string) $proof['occurred_at']
                : now()->toIso8601String(),
            'evidence_reference' => isset($proof['reference'])
                ? (string) $proof['reference']
                : null,
        ], $eventId, $request->getContent(), $request);

        return ApiResponse::success([
            'event_id' => $result['receipt']->event_id,
            'order_id' => $result['shipment_leg']->order_id,
            'sub_order_id' => $result['shipment_leg']->sub_order_id,
            'shipment_leg_id' => $result['shipment_leg']->id,
            'status' => 'delivery_confirmed',
            'replayed' => $result['replayed'],
        ]);
    }
}
