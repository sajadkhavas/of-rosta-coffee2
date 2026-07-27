<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryConfirmationSource;
use App\Http\Requests\Delivery\CarrierDeliveryWebhookRequest;
use App\Http\Requests\Delivery\ConfirmDeliveryRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\ShipmentLeg;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Fulfillment\DeliveryConfirmationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
        DeliveryConfirmationService $deliveries,
    ): JsonResponse {
        $secret = (string) config('rosta.settlement.carrier_webhook_secret', '');
        $provided = trim((string) $request->header('X-Rosta-Carrier-Signature', ''));
        $expected = $secret === '' ? '' : hash_hmac('sha256', $request->getContent(), $secret);
        if ($secret === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            throw new AccessDeniedHttpException('Invalid carrier webhook signature.');
        }

        $validated = $request->validated();
        $leg = ShipmentLeg::query()
            ->where('carrier', $validated['carrier'])
            ->where('tracking_code', $validated['tracking_code'])
            ->orderByDesc('sequence')
            ->firstOrFail();

        $order = $deliveries->confirm(
            null,
            $leg,
            DeliveryConfirmationSource::Carrier,
            [
                'idempotency_key' => $validated['idempotency_key'],
                'proof_type' => $validated['proof_type'],
                'proof_payload' => $validated['proof_payload'] ?? null,
            ],
            $request,
        );

        return ApiResponse::success([
            'order_id' => $order->id,
            'sub_order_id' => $leg->sub_order_id,
            'shipment_leg_id' => $leg->id,
            'status' => 'delivery_confirmed',
        ]);
    }
}
