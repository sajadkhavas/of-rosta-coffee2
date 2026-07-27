<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Checkout\OrderIndexRequest;
use App\Http\Requests\Fulfillment\ReportFulfillmentIncidentRequest;
use App\Http\Requests\Fulfillment\UpdateFulfillmentRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Checkout\OrderService;
use App\Services\Fulfillment\FulfillmentIncidentService;
use App\Services\Fulfillment\FulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SellerOrderController
{
    public function index(
        OrderIndexRequest $request,
        string $roasteryId,
        CatalogAccess $access,
        OrderService $orders,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $validated = $request->validated();
        $query = Order::query()
            ->whereHas('subOrders', static fn ($query) => $query->where('roastery_id', $roastery->id))
            ->orderByDesc('created_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $page = $query->paginate(
            perPage: (int) ($validated['per_page'] ?? 50),
            page: (int) ($validated['page'] ?? 1),
        );

        $page->getCollection()->transform(
            fn (Order $order): Order => $this->scopeOrder(
                $orders->loadOrder($order),
                $roastery->id,
            ),
        );

        return OrderResource::collection($page);
    }

    public function show(
        Request $request,
        string $roasteryId,
        string $orderId,
        CatalogAccess $access,
        OrderService $orders,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $order = Order::query()
            ->whereHas('subOrders', static fn ($query) => $query->where('roastery_id', $roastery->id))
            ->findOrFail($orderId);

        return new OrderResource($this->scopeOrder(
            $orders->loadOrder($order),
            $roastery->id,
        ));
    }

    public function transition(
        UpdateFulfillmentRequest $request,
        string $roasteryId,
        string $orderId,
        CatalogAccess $access,
        FulfillmentService $fulfillment,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        return new OrderResource($this->scopeOrder(
            $fulfillment->transition(
                $user,
                $roastery,
                $orderId,
                $request->validated(),
                $request,
                allowDelivery: false,
            ),
            $roastery->id,
        ));
    }

    public function reportIncident(
        ReportFulfillmentIncidentRequest $request,
        string $roasteryId,
        string $orderId,
        CatalogAccess $access,
        FulfillmentIncidentService $incidents,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        return new OrderResource($this->scopeOrder(
            $incidents->report(
                $user,
                $roastery,
                $orderId,
                $request->validated(),
                $request,
            ),
            $roastery->id,
        ));
    }

    private function scopeOrder(Order $order, string $roasteryId): Order
    {
        $subOrders = $order->subOrders
            ->filter(static fn ($subOrder): bool => $subOrder->roastery_id === $roasteryId)
            ->values();
        $subOrderIds = $subOrders->pluck('id')->all();
        $order->setRelation('subOrders', $subOrders);
        $order->setRelation(
            'events',
            $order->events
                ->filter(static fn ($event): bool => $event->sub_order_id === null
                    || in_array($event->sub_order_id, $subOrderIds, true))
                ->values(),
        );

        return $order;
    }
}
