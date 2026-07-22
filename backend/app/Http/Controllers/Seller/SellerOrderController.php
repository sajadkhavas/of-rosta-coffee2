<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Checkout\OrderIndexRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Checkout\OrderService;
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
            ->where('roastery_id', $roastery->id)
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
            ->where('roastery_id', $roastery->id)
            ->findOrFail($orderId);

        return new OrderResource($orders->loadOrder($order));
    }
}
