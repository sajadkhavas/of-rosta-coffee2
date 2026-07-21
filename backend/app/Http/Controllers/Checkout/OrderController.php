<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Requests\Checkout\CancelOrderRequest;
use App\Http\Requests\Checkout\CreateOrderRequest;
use App\Http\Requests\Checkout\OrderIndexRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\OrderCancellationService;
use App\Services\Checkout\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrderController
{
    public function index(
        OrderIndexRequest $request,
        OrderService $orders,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $query = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $page = $query->paginate(
            perPage: (int) ($validated['per_page'] ?? 24),
            page: (int) ($validated['page'] ?? 1),
        );

        $page->getCollection()->transform(
            static fn (Order $order): Order => $orders->loadOrder($order),
        );

        return OrderResource::collection($page);
    }

    public function store(
        CreateOrderRequest $request,
        OrderService $orders,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $order = $orders->create(
            $user,
            (string) $request->validated('quote_id'),
            (string) $request->validated('idempotency_key'),
            $request->validated('notes'),
            $request,
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        string $orderId,
        OrderService $orders,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();

        $order = Order::query()
            ->where('user_id', $user->id)
            ->findOrFail($orderId);

        return new OrderResource($orders->loadOrder($order));
    }

    public function cancel(
        CancelOrderRequest $request,
        string $orderId,
        OrderCancellationService $cancellations,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();

        return new OrderResource($cancellations->cancelByCustomer(
            $user,
            $orderId,
            $request->validated('reason'),
            $request,
        ));
    }
}
