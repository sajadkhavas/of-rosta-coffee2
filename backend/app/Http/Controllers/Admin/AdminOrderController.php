<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Checkout\OrderIndexRequest;
use App\Http\Requests\Fulfillment\ResolveFulfillmentIncidentRequest;
use App\Http\Requests\Fulfillment\UpdateFulfillmentRequest;
use App\Http\Resources\OrderResource;
use App\Models\FulfillmentIncident;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Checkout\OrderService;
use App\Services\Fulfillment\FulfillmentIncidentService;
use App\Services\Fulfillment\FulfillmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminOrderController
{
    public function index(
        OrderIndexRequest $request,
        CatalogAccess $access,
        OrderService $orders,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $validated = $request->validated();
        $query = Order::query()
            ->with(['subOrder.shipment', 'subOrder.statusHistory.actor'])
            ->orderByDesc('created_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $page = $query->paginate(
            perPage: (int) ($validated['per_page'] ?? 50),
            page: (int) ($validated['page'] ?? 1),
        );

        $page->getCollection()->transform(
            static fn (Order $order): Order => $orders->loadOrder($order),
        );

        return OrderResource::collection($page);
    }

    public function show(
        Request $request,
        string $orderId,
        CatalogAccess $access,
        OrderService $orders,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $order = Order::query()
            ->with(['subOrder.shipment', 'subOrder.statusHistory.actor'])
            ->findOrFail($orderId);

        return new OrderResource($orders->loadOrder($order));
    }

    public function transition(
        UpdateFulfillmentRequest $request,
        string $orderId,
        CatalogAccess $access,
        FulfillmentService $fulfillment,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $order = Order::query()->with('subOrders.roastery')->findOrFail($orderId);
        $subOrder = $order->subOrders->first();
        if (! $subOrder instanceof SubOrder || $subOrder->roastery === null) {
            abort(404);
        }

        return new OrderResource($fulfillment->transition(
            $user,
            $subOrder->roastery,
            $order->id,
            $request->validated(),
            $request,
            allowDelivery: true,
        ));
    }

    public function incidents(
        Request $request,
        CatalogAccess $access,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $status = trim((string) $request->query('status', 'open'));
        $query = FulfillmentIncident::query()
            ->with(['order', 'subOrder', 'roastery', 'reporter', 'resolver', 'refundAttempt'])
            ->latest('reported_at');
        if (in_array($status, ['open', 'resolved'], true)) {
            $query->where('status', $status);
        }
        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()
                ->map(static fn (FulfillmentIncident $incident): array => [
                    'id' => $incident->id,
                    'order_id' => $incident->order_id,
                    'order_number' => $incident->order?->order_number,
                    'sub_order_id' => $incident->sub_order_id,
                    'roastery' => [
                        'id' => $incident->roastery_id,
                        'name' => $incident->roastery?->name,
                    ],
                    'sub_order_status' => $incident->subOrder?->status->value,
                    'sla_status' => $incident->subOrder?->sla_status?->value,
                    'preparation_due_at' => $incident->subOrder?->preparation_due_at?->toIso8601String(),
                    'handoff_due_at' => $incident->subOrder?->handoff_due_at?->toIso8601String(),
                    'status' => $incident->status->value,
                    'code' => $incident->code,
                    'severity' => $incident->severity,
                    'description' => $incident->description,
                    'resolution' => $incident->resolution?->value,
                    'resolution_note' => $incident->resolution_note,
                    'refund_attempt_id' => $incident->refund_attempt_id,
                    'reported_at' => $incident->reported_at->toIso8601String(),
                    'resolved_at' => $incident->resolved_at?->toIso8601String(),
                ])
                ->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function resolveIncident(
        ResolveFulfillmentIncidentRequest $request,
        string $incidentId,
        CatalogAccess $access,
        FulfillmentIncidentService $incidents,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        FulfillmentIncident::query()->findOrFail($incidentId);

        return new OrderResource($incidents->resolve(
            $user,
            $incidentId,
            $request->validated(),
            $request,
        ));
    }
}
