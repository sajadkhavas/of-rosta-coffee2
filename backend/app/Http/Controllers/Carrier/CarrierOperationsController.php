<?php

namespace App\Http\Controllers\Carrier;

use App\Enums\CarrierEventType;
use App\Http\Requests\Carrier\CarrierEventWebhookRequest;
use App\Http\Requests\Carrier\ManageShipmentLegCarrierRequest;
use App\Http\Requests\Delivery\CarrierDeliveryWebhookRequest;
use App\Models\ShipmentLeg;
use App\Models\User;
use App\Services\Carrier\CarrierOperationsService;
use App\Services\Catalog\CatalogAccess;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CarrierOperationsController
{
    public function webhook(
        CarrierEventWebhookRequest $request,
        CarrierOperationsService $operations,
    ): JsonResponse {
        $eventId = (string) $request->attributes->get('carrier_event_id');
        $result = $operations->ingest(
            $request->validated(),
            $eventId,
            $request->getContent(),
            $request,
        );

        return ApiResponse::success([
            'event_id' => $result['receipt']->event_id,
            'shipment_leg_id' => $result['shipment_leg']->id,
            'status' => $result['shipment_leg']->status->value,
            'replayed' => $result['replayed'],
        ]);
    }

    public function legacyDelivery(
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
            'occurred_at' => isset($proof['occurred_at']) ? (string) $proof['occurred_at'] : now()->toIso8601String(),
            'evidence_reference' => isset($proof['reference']) ? (string) $proof['reference'] : null,
        ], $eventId, $request->getContent(), $request);

        return ApiResponse::success([
            'event_id' => $result['receipt']->event_id,
            'shipment_leg_id' => $result['shipment_leg']->id,
            'status' => 'delivery_confirmed',
            'replayed' => $result['replayed'],
        ]);
    }

    public function manage(
        ManageShipmentLegCarrierRequest $request,
        string $shipmentLegId,
        CatalogAccess $access,
        CarrierOperationsService $operations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $leg = ShipmentLeg::query()->findOrFail($shipmentLegId);
        $updated = $operations->manage($user, $leg, $request->validated(), $request);

        return ApiResponse::success([
            'id' => $updated->id,
            'order_id' => $updated->order_id,
            'sub_order_id' => $updated->sub_order_id,
            'carrier' => $updated->carrier,
            'tracking_code' => $updated->tracking_code,
            'status' => $updated->status->value,
            'picked_up_at' => $updated->picked_up_at?->toIso8601String(),
            'delivered_at' => $updated->delivered_at?->toIso8601String(),
        ]);
    }
}
