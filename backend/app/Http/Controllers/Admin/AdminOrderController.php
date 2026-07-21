<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Checkout\OrderIndexRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Checkout\OrderService;
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
        $query = Order::query()->orderByDesc('created_at');

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

        return new OrderResource($orders->loadOrder(
            Order::query()->findOrFail($orderId),
        ));
    }
}
