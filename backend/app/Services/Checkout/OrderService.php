<?php

namespace App\Services\Checkout;

use App\Enums\IdempotencyStatus;
use App\Enums\OrderItemServiceStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\QuotePurpose;
use App\Enums\ReservationStatus;
use App\Enums\SettlementAllocationStatus;
use App\Enums\ShipmentLegStatus;
use App\Enums\SubOrderAcceptanceStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\ApiDomainException;
use App\Models\CheckoutQuote;
use App\Models\Coupon;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderIdempotencyKey;
use App\Models\OrderItem;
use App\Models\OrderItemService;
use App\Models\OrderTaxLine;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\SettlementAllocation;
use App\Models\ShipmentLeg;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Hub\RostaHubOperationsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class OrderService
{
    public function __construct(
        private readonly CheckoutHasher $hasher,
        private readonly AuditRecorder $audit,
        private readonly RoasteryGrindingSelection $grinding,
        private readonly RostaHubOperationsService $hubOperations,
    ) {}

    public function create(
        User $user,
        string $quoteId,
        string $idempotencyKey,
        ?string $notes,
        Request $request,
    ): Order {
        $requestHash = $this->hasher->hash([
            'quote_id' => $quoteId,
            'notes' => $notes,
        ]);

        return DB::transaction(function () use (
            $user,
            $quoteId,
            $idempotencyKey,
            $notes,
            $request,
            $requestHash,
        ): Order {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $record = OrderIdempotencyKey::query()
                ->where('user_id', $user->id)
                ->where('key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($record instanceof OrderIdempotencyKey) {
                if (! hash_equals($record->request_hash, $requestHash)) {
                    throw new ApiDomainException(
                        'order.idempotency_conflict',
                        'این کلید Idempotency قبلاً با درخواست دیگری استفاده شده است.',
                        409,
                    );
                }

                if (
                    $record->status === IdempotencyStatus::Completed
                    && $record->order_id !== null
                ) {
                    return $this->loadOrder(
                        Order::query()
                            ->where('user_id', $user->id)
                            ->findOrFail($record->order_id),
                    );
                }

                throw new ApiDomainException(
                    'order.idempotency_in_progress',
                    'درخواست ایجاد سفارش با این کلید در حال پردازش است.',
                    409,
                );
            }

            $record = OrderIdempotencyKey::query()->create([
                'user_id' => $user->id,
                'key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'status' => IdempotencyStatus::Processing,
                'expires_at' => now()->addHours(
                    (int) config('rosta.checkout.idempotency_ttl_hours', 24),
                ),
            ]);

            $quote = CheckoutQuote::query()
                ->with([
                    'groups.items.services',
                    'groups.roastery',
                    'items',
                ])
                ->lockForUpdate()
                ->findOrFail($quoteId);

            if (
                $quote->purpose !== QuotePurpose::Checkout
                || $quote->user_id !== $user->id
            ) {
                abort(404);
            }

            if ($quote->consumed_at !== null) {
                throw new ApiDomainException(
                    'checkout.quote_consumed',
                    'این Quote قبلاً مصرف شده است.',
                    409,
                );
            }

            if ($quote->expires_at->isPast()) {
                throw new ApiDomainException(
                    'checkout.quote_expired',
                    'زمان اعتبار Quote به پایان رسیده است.',
                    409,
                );
            }

            if ($quote->groups->isEmpty() || $quote->items->isEmpty()) {
                throw new ApiDomainException(
                    'checkout.quote_empty',
                    'Quote فاقد گروه یا آیتم معتبر است.',
                    409,
                );
            }

            $this->assertQuoteTotals($quote);

            $variantIds = $quote->items
                ->pluck('variant_id')
                ->filter()
                ->sort()
                ->values();

            $variants = ProductVariant::query()
                ->with(['product.roastery', 'product.latestRoastBatch'])
                ->whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($variants->count() !== $variantIds->count()) {
                throw new ApiDomainException(
                    'checkout.variant_changed',
                    'یکی از گزینه‌های Quote دیگر وجود ندارد.',
                    409,
                );
            }

            foreach ($quote->groups as $group) {
                if (! $group->roastery?->isPubliclyVisible()) {
                    throw new ApiDomainException(
                        'checkout.catalog_changed',
                        'یکی از روستری‌های سفارش دیگر فعال نیست.',
                        409,
                    );
                }

                foreach ($group->items as $quoteItem) {
                    $variant = $variants->get($quoteItem->variant_id);
                    if (! $variant instanceof ProductVariant) {
                        throw new ApiDomainException(
                            'checkout.variant_changed',
                            'یکی از گزینه‌های Quote دیگر وجود ندارد.',
                            409,
                        );
                    }

                    $product = $variant->product;
                    if (
                        ! $variant->is_active
                        || $variant->price !== $quoteItem->unit_price
                        || $product->status !== ProductStatus::Published
                        || $product->published_at === null
                        || $product->roastery_id !== $group->roastery_id
                        || ! $product->roastery->isPubliclyVisible()
                    ) {
                        throw new ApiDomainException(
                            'checkout.catalog_changed',
                            'قیمت یا وضعیت یکی از محصولات تغییر کرده است.',
                            409,
                        );
                    }

                    if ($variant->availableQuantity() < $quoteItem->quantity) {
                        throw new ApiDomainException(
                            'checkout.insufficient_stock',
                            'موجودی یکی از گزینه‌ها برای ثبت سفارش کافی نیست.',
                            409,
                            ['variant_id' => [$variant->id]],
                        );
                    }

                    if ($quoteItem->roast_batch_id !== null) {
                        $batch = RoastBatch::query()
                            ->where('id', $quoteItem->roast_batch_id)
                            ->where('product_id', $product->id)
                            ->where('is_active', true)
                            ->lockForUpdate()
                            ->first();

                        if (! $batch instanceof RoastBatch) {
                            throw new ApiDomainException(
                                'checkout.roast_batch_changed',
                                'بچ رست انتخاب‌شده دیگر قابل استفاده نیست.',
                                409,
                            );
                        }
                    }
                }
            }

            $this->reserveCoupon($quote);

            $singleRoasteryId = $quote->groups->count() === 1
                ? $quote->groups->first()?->roastery_id
                : null;

            $order = new Order([
                'user_id' => $user->id,
                'roastery_id' => $singleRoasteryId,
                'quote_id' => $quote->id,
                'status' => OrderStatus::AwaitingPayment,
                'address_snapshot' => $quote->address_snapshot,
                'notes' => $notes,
                'subtotal' => $quote->subtotal,
                'shipping_total' => $quote->shipping_total,
                'discount_total' => $quote->discount_total,
                'tax_total' => $quote->tax_total,
                'commission_total' => $quote->commission_total,
                'grand_total' => $quote->grand_total,
                'financial_snapshot' => $quote->financial_snapshot,
                'currency' => $quote->currency,
                'placed_at' => now(),
            ]);
            $order->id = (string) Str::ulid();
            $order->order_number = 'R-'.now()->format('ymd').'-'.strtoupper(substr($order->id, -8));
            $order->save();

            $reservationExpiresAt = now()->addMinutes(
                (int) config('rosta.checkout.reservation_ttl_minutes', 20),
            );
            $shipmentSequence = 1;

            foreach ($quote->groups as $group) {
                $hubRoute = is_array($group->pricing_snapshot)
                    ? ($group->pricing_snapshot['hub_route'] ?? null)
                    : null;
                $shippingFinancial = is_array($group->financial_snapshot)
                    ? ($group->financial_snapshot['components']['shipping'] ?? [])
                    : [];
                $subOrder = SubOrder::query()->create([
                    'order_id' => $order->id,
                    'roastery_id' => $group->roastery_id,
                    'status' => SubOrderStatus::AwaitingPayment,
                    'acceptance_status' => SubOrderAcceptanceStatus::AwaitingPayment,
                    'subtotal' => $group->subtotal,
                    'shipping_total' => $group->shipping_total,
                    'packaging_total' => $group->packaging_total,
                    'grinding_total' => $group->grinding_total,
                    'discount_total' => $group->discount_total,
                    'tax_total' => $group->tax_total,
                    'grand_total' => $group->grand_total,
                    'commission_total' => $group->commission_total,
                    'payable_total' => $group->payable_total,
                    'currency' => $group->currency,
                    'financial_snapshot' => $group->financial_snapshot,
                ]);

                $hubGrindingService = null;

                $itemBases = $group->items
                    ->mapWithKeys(static fn ($item): array => [
                        (string) $item->id => (int) $item->line_total,
                    ])
                    ->all();
                $itemDiscounts = $this->allocateMoney($group->discount_total, $itemBases);

                foreach ($group->items as $quoteItem) {
                    $variant = $variants->get($quoteItem->variant_id);
                    if (! $variant instanceof ProductVariant) {
                        throw new LogicException('Locked quote variant disappeared.');
                    }

                    $orderItem = OrderItem::query()->create([
                        'order_id' => $order->id,
                        'sub_order_id' => $subOrder->id,
                        'product_id' => $quoteItem->product_id,
                        'variant_id' => $quoteItem->variant_id,
                        'roast_batch_id' => $quoteItem->roast_batch_id,
                        'quantity' => $quoteItem->quantity,
                        'unit_price' => $quoteItem->unit_price,
                        'line_total' => $quoteItem->line_total,
                        'discount_amount' => $quoteItem->discount_amount,
                        'tax_amount' => $quoteItem->tax_amount,
                        'commission_amount' => $quoteItem->commission_amount,
                        'net_amount' => $quoteItem->net_amount,
                        'financial_snapshot' => $quoteItem->financial_snapshot,
                        'product_snapshot' => $quoteItem->product_snapshot,
                        'variant_snapshot' => $quoteItem->variant_snapshot,
                        'roast_batch_snapshot' => $quoteItem->roast_batch_snapshot,
                    ]);

                    $variant->forceFill([
                        'stock_reserved' => $variant->stock_reserved + $quoteItem->quantity,
                    ])->save();

                    InventoryReservation::query()->create([
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'variant_id' => $variant->id,
                        'roast_batch_id' => $quoteItem->roast_batch_id,
                        'quantity' => $quoteItem->quantity,
                        'status' => ReservationStatus::Active,
                        'expires_at' => $reservationExpiresAt,
                    ]);

                    $discount = $itemDiscounts[(string) $quoteItem->id] ?? 0;
                    if ($discount !== $quoteItem->discount_amount) {
                        throw new ApiDomainException('mixed_state_conflict', 'تخصیص تخفیف Quote با snapshot مالی ناسازگار است.', 409);
                    }
                    $productAllocation = SettlementAllocation::query()->create([
                        'order_id' => $order->id,
                        'sub_order_id' => $subOrder->id,
                        'order_item_id' => $orderItem->id,
                        'allocation_type' => 'product',
                        'owner_type' => 'roastery',
                        'owner_id' => $group->roastery_id,
                        'status' => SettlementAllocationStatus::Held,
                        'gross_amount' => $quoteItem->line_total,
                        'discount_amount' => $discount,
                        'tax_amount' => $quoteItem->tax_amount,
                        'commission_amount' => $quoteItem->commission_amount,
                        'net_amount' => $quoteItem->net_amount,
                        'currency' => $group->currency,
                        'tax_code' => $quoteItem->financial_snapshot['tax_rule']['code'] ?? null,
                        'pricing_version' => 'ps4a-financial-truth-v1',
                        'source_reference' => 'quote_item:'.$quoteItem->id,
                        'idempotency_key' => 'order:'.$order->id.':item:'.$orderItem->id.':product',
                        'metadata' => [
                            'quote_id' => $quote->id,
                            'quote_group_id' => $group->id,
                        ],
                        'financial_snapshot' => $quoteItem->financial_snapshot,
                    ]);
                    $this->recordTaxLine($productAllocation, $order, $subOrder, $orderItem, null, 'product', $quoteItem->financial_snapshot);
                    $this->recordCommissionAllocation($productAllocation, $order, $subOrder, $orderItem, null, $quoteItem->commission_amount, $quoteItem->financial_snapshot);

                    foreach ($quoteItem->services as $quoteService) {
                        if ($quoteService->service_type === 'grinding') {
                            $this->grinding->assertQuoteServiceOrderable(
                                $quoteService,
                                $variant,
                                $quoteItem->quantity,
                                $quote->address_snapshot,
                            );
                        }

                        $orderService = OrderItemService::query()->create([
                            'order_id' => $order->id,
                            'sub_order_id' => $subOrder->id,
                            'order_item_id' => $orderItem->id,
                            'service_type' => $quoteService->service_type,
                            'provider_type' => $quoteService->provider_type,
                            'provider_roastery_id' => $quoteService->provider_roastery_id,
                            'provider_hub_id' => $quoteService->provider_hub_id,
                            'grinding_profile_id' => $quoteService->grinding_profile_id,
                            'status' => OrderItemServiceStatus::Requested,
                            'service_fee' => $quoteService->service_fee,
                            'packaging_fee' => $quoteService->packaging_fee,
                            'shipping_fee' => $quoteService->shipping_fee,
                            'tax_amount' => $quoteService->tax_amount,
                            'commission_amount' => $quoteService->commission_amount,
                            'total_amount' => $quoteService->total_amount,
                            'currency' => $quoteService->currency,
                            'pricing_snapshot' => $quoteService->pricing_snapshot,
                            'service_snapshot' => $quoteService->service_snapshot,
                            'financial_snapshot' => $quoteService->financial_snapshot,
                        ]);

                        if ($orderService->packaging_fee > 0) {
                            $packagingAllocation = SettlementAllocation::query()->create([
                                'order_id' => $order->id,
                                'sub_order_id' => $subOrder->id,
                                'order_item_id' => $orderItem->id,
                                'order_item_service_id' => $orderService->id,
                                'allocation_type' => 'packaging',
                                'owner_type' => 'roastery',
                                'owner_id' => $group->roastery_id,
                                'status' => SettlementAllocationStatus::Held,
                                'gross_amount' => $orderService->packaging_fee,
                                'discount_amount' => 0,
                                'tax_amount' => $orderService->tax_amount,
                                'commission_amount' => $orderService->commission_amount,
                                'net_amount' => $orderService->total_amount - $orderService->commission_amount,
                                'currency' => $orderService->currency,
                                'tax_code' => $orderService->financial_snapshot['tax_rule']['code'] ?? null,
                                'pricing_version' => 'ps4a-financial-truth-v1',
                                'source_reference' => 'quote_service:'.$quoteService->id,
                                'idempotency_key' => 'order:'.$order->id.':service:'.$orderService->id.':packaging',
                                'metadata' => [
                                    'quote_id' => $quote->id,
                                    'quote_group_id' => $group->id,
                                    'quote_item_service_id' => $quoteService->id,
                                ],
                                'financial_snapshot' => $orderService->financial_snapshot,
                            ]);
                            $this->recordTaxLine($packagingAllocation, $order, $subOrder, $orderItem, $orderService, 'packaging', $orderService->financial_snapshot);
                            $this->recordCommissionAllocation($packagingAllocation, $order, $subOrder, $orderItem, $orderService, $orderService->commission_amount, $orderService->financial_snapshot);
                        }

                        if ($orderService->service_type === 'grinding') {
                            $isHubGrinding = $orderService->provider_type === 'rosta_hub';
                            if ($isHubGrinding && ! $hubGrindingService instanceof OrderItemService) {
                                $hubGrindingService = $orderService;
                            }

                            if ($orderService->service_fee > 0) {
                                $grindingAllocation = SettlementAllocation::query()->create([
                                    'order_id' => $order->id,
                                    'sub_order_id' => $subOrder->id,
                                    'order_item_id' => $orderItem->id,
                                    'order_item_service_id' => $orderService->id,
                                    'allocation_type' => 'grinding',
                                    'owner_type' => $isHubGrinding ? 'rosta' : 'roastery',
                                    'owner_id' => $isHubGrinding ? null : $group->roastery_id,
                                    'status' => SettlementAllocationStatus::Held,
                                    'gross_amount' => $orderService->service_fee,
                                    'discount_amount' => 0,
                                    'tax_amount' => $orderService->tax_amount,
                                    'commission_amount' => $orderService->commission_amount,
                                    'net_amount' => $orderService->total_amount - $orderService->commission_amount,
                                    'currency' => $orderService->currency,
                                    'tax_code' => $orderService->financial_snapshot['tax_rule']['code'] ?? null,
                                    'pricing_version' => 'ps4a-financial-truth-v1',
                                    'source_reference' => 'quote_service:'.$quoteService->id,
                                    'idempotency_key' => 'order:'.$order->id.':service:'.$orderService->id.':grinding',
                                    'metadata' => [
                                        'quote_id' => $quote->id,
                                        'quote_group_id' => $group->id,
                                        'quote_item_service_id' => $quoteService->id,
                                        'provider_type' => $orderService->provider_type,
                                        'provider_hub_id' => $orderService->provider_hub_id,
                                    ],
                                    'financial_snapshot' => $orderService->financial_snapshot,
                                ]);
                                $this->recordTaxLine($grindingAllocation, $order, $subOrder, $orderItem, $orderService, 'grinding', $orderService->financial_snapshot);
                                $this->recordCommissionAllocation($grindingAllocation, $order, $subOrder, $orderItem, $orderService, $orderService->commission_amount, $orderService->financial_snapshot);
                            }

                            $this->appendEvent(
                                order: $order,
                                user: $user,
                                request: $request,
                                type: 'grinding.requested',
                                title: 'سرویس آسیاب ثبت شد',
                                nextState: OrderItemServiceStatus::Requested->value,
                                subOrder: $subOrder,
                                orderItem: $orderItem,
                                orderItemService: $orderService,
                            );
                        }
                    }
                }

                if (is_array($hubRoute) && $hubGrindingService instanceof OrderItemService) {
                    $route = $hubRoute['route'] ?? [];
                    $hub = $hubRoute['hub'] ?? [];
                    $inboundFee = (int) ($route['inbound_shipping_fee'] ?? 0);
                    $outboundFee = (int) ($route['outbound_shipping_fee'] ?? 0);

                    $inboundLeg = ShipmentLeg::query()->create([
                        'order_id' => $order->id,
                        'sub_order_id' => $subOrder->id,
                        'order_item_service_id' => $hubGrindingService->id,
                        'route_type' => 'roastery_to_rosta_hub',
                        'sequence' => $shipmentSequence++,
                        'status' => ShipmentLegStatus::Planned,
                        'charge_owner_type' => 'rosta',
                        'charge_owner_id' => null,
                        'gross_amount' => $inboundFee,
                        'tax_amount' => 0,
                        'total_amount' => $inboundFee,
                        'currency' => $group->currency,
                        'origin_snapshot' => [
                            'type' => 'roastery',
                            'id' => $group->roastery_id,
                            'name' => $group->roastery?->name,
                            'city' => $group->roastery?->city,
                        ],
                        'destination_snapshot' => [
                            'type' => 'rosta_hub',
                            ...$hub,
                        ],
                        'planned_at' => now(),
                    ]);

                    $outboundLeg = ShipmentLeg::query()->create([
                        'order_id' => $order->id,
                        'sub_order_id' => $subOrder->id,
                        'order_item_service_id' => $hubGrindingService->id,
                        'route_type' => 'rosta_hub_to_customer',
                        'sequence' => $shipmentSequence++,
                        'status' => ShipmentLegStatus::Planned,
                        'charge_owner_type' => 'rosta',
                        'charge_owner_id' => null,
                        'gross_amount' => $outboundFee,
                        'tax_amount' => 0,
                        'total_amount' => $outboundFee,
                        'currency' => $group->currency,
                        'origin_snapshot' => [
                            'type' => 'rosta_hub',
                            ...$hub,
                        ],
                        'destination_snapshot' => $quote->address_snapshot,
                        'planned_at' => now(),
                    ]);

                    $legBases = [
                        (string) $inboundLeg->id => $inboundLeg->gross_amount,
                        (string) $outboundLeg->id => $outboundLeg->gross_amount,
                    ];
                    $legTaxes = $this->allocateMoney((int) ($shippingFinancial['tax_amount'] ?? 0), $legBases);
                    $legCommissions = $this->allocateMoney((int) ($shippingFinancial['commission_amount'] ?? 0), $legBases);
                    foreach ([$inboundLeg, $outboundLeg] as $leg) {
                        if ($leg->gross_amount <= 0) {
                            continue;
                        }

                        $legTax = $legTaxes[(string) $leg->id] ?? 0;
                        $legCommission = $legCommissions[(string) $leg->id] ?? 0;
                        $leg->forceFill([
                            'tax_amount' => $legTax,
                            'total_amount' => $leg->gross_amount + $legTax,
                        ])->save();
                        $legFinancial = [
                            ...$shippingFinancial,
                            'gross_amount' => $leg->gross_amount,
                            'tax_amount' => $legTax,
                            'commission_amount' => $legCommission,
                            'payable_amount' => $leg->total_amount - $legCommission,
                            'allocation_scope' => $leg->route_type,
                        ];
                        $shippingAllocation = SettlementAllocation::query()->create([
                            'order_id' => $order->id,
                            'sub_order_id' => $subOrder->id,
                            'order_item_service_id' => $hubGrindingService->id,
                            'shipment_leg_id' => $leg->id,
                            'allocation_type' => 'shipping',
                            'owner_type' => 'rosta',
                            'owner_id' => null,
                            'status' => SettlementAllocationStatus::Held,
                            'gross_amount' => $leg->gross_amount,
                            'discount_amount' => 0,
                            'tax_amount' => $legTax,
                            'commission_amount' => $legCommission,
                            'net_amount' => $leg->total_amount - $legCommission,
                            'currency' => $leg->currency,
                            'tax_code' => $shippingFinancial['tax_rule']['code'] ?? null,
                            'pricing_version' => 'ps4a-financial-truth-v1',
                            'source_reference' => 'shipment_leg:'.$leg->id,
                            'idempotency_key' => 'order:'.$order->id.':shipment-leg:'.$leg->id.':shipping',
                            'metadata' => [
                                'quote_id' => $quote->id,
                                'quote_group_id' => $group->id,
                                'route_type' => $leg->route_type,
                            ],
                            'financial_snapshot' => $legFinancial,
                        ]);
                        $this->recordTaxLine($shippingAllocation, $order, $subOrder, null, $hubGrindingService, 'shipping', $legFinancial);
                    }

                    $this->appendEvent(
                        order: $order,
                        user: $user,
                        request: $request,
                        type: 'hub.route_planned',
                        title: 'مسیر آسیاب هاب رستا برنامه‌ریزی شد',
                        nextState: ShipmentLegStatus::Planned->value,
                        subOrder: $subOrder,
                        orderItemService: $hubGrindingService,
                    );

                    $hubOrderItem = $hubGrindingService->orderItem()->firstOrFail();
                    $this->hubOperations->createForRoute(
                        $hubGrindingService,
                        $inboundLeg,
                        $outboundLeg,
                        (int) ($hubOrderItem->variant_snapshot['weight_grams'] ?? 0),
                        $hubOrderItem->quantity,
                    );
                } else {
                    $shippingTax = (int) ($shippingFinancial['tax_amount'] ?? 0);
                    $shippingCommission = (int) ($shippingFinancial['commission_amount'] ?? 0);
                    $shipmentLeg = ShipmentLeg::query()->create([
                        'order_id' => $order->id,
                        'sub_order_id' => $subOrder->id,
                        'route_type' => 'roastery_to_customer',
                        'sequence' => $shipmentSequence++,
                        'status' => ShipmentLegStatus::Planned,
                        'charge_owner_type' => 'roastery',
                        'charge_owner_id' => $group->roastery_id,
                        'gross_amount' => $group->shipping_total,
                        'tax_amount' => $shippingTax,
                        'total_amount' => $group->shipping_total + $shippingTax,
                        'currency' => $group->currency,
                        'origin_snapshot' => [
                            'type' => 'roastery',
                            'id' => $group->roastery_id,
                            'name' => $group->roastery?->name,
                            'city' => $group->roastery?->city,
                        ],
                        'destination_snapshot' => $quote->address_snapshot,
                        'planned_at' => now(),
                    ]);

                    $shippingAllocation = SettlementAllocation::query()->create([
                        'order_id' => $order->id,
                        'sub_order_id' => $subOrder->id,
                        'shipment_leg_id' => $shipmentLeg->id,
                        'allocation_type' => 'shipping',
                        'owner_type' => 'roastery',
                        'owner_id' => $group->roastery_id,
                        'status' => SettlementAllocationStatus::Held,
                        'gross_amount' => $group->shipping_total,
                        'discount_amount' => 0,
                        'tax_amount' => $shippingTax,
                        'commission_amount' => $shippingCommission,
                        'net_amount' => $group->shipping_total + $shippingTax - $shippingCommission,
                        'currency' => $group->currency,
                        'tax_code' => $shippingFinancial['tax_rule']['code'] ?? null,
                        'pricing_version' => 'ps4a-financial-truth-v1',
                        'source_reference' => 'quote_group:'.$group->id.':shipping',
                        'idempotency_key' => 'order:'.$order->id.':sub-order:'.$subOrder->id.':shipping',
                        'metadata' => [
                            'quote_id' => $quote->id,
                            'quote_group_id' => $group->id,
                        ],
                        'financial_snapshot' => $shippingFinancial,
                    ]);
                    $this->recordTaxLine($shippingAllocation, $order, $subOrder, null, null, 'shipping', $shippingFinancial);
                    $this->recordCommissionAllocation($shippingAllocation, $order, $subOrder, null, null, $shippingCommission, $shippingFinancial);
                }

                $this->appendEvent(
                    order: $order,
                    user: $user,
                    request: $request,
                    type: 'sub_order.awaiting_payment',
                    title: 'زیرسفارش در انتظار پرداخت است',
                    nextState: OrderStatus::AwaitingPayment->value,
                    subOrder: $subOrder,
                );
            }

            $this->appendEvent(
                order: $order,
                user: $user,
                request: $request,
                type: 'order.created',
                title: 'سفارش ثبت شد',
                nextState: OrderStatus::AwaitingPayment->value,
            );

            $quote->forceFill(['consumed_at' => now()])->save();

            $record->forceFill([
                'status' => IdempotencyStatus::Completed,
                'order_id' => $order->id,
            ])->save();

            $this->audit->record(
                'checkout.marketplace_order.created',
                actor: $user,
                auditable: $order,
                metadata: [
                    'quote_id' => $quote->id,
                    'roastery_count' => $quote->groups->count(),
                    'grand_total' => $quote->grand_total,
                    'reservation_expires_at' => $reservationExpiresAt->toIso8601String(),
                ],
                request: $request,
            );

            return $this->loadOrder($order);
        }, attempts: 3);
    }

    public function loadOrder(Order $order): Order
    {
        return $order->load([
            'subOrders.roastery.logo',
            'subOrders.roastery.cover',
            'subOrders.items.services.grindingProfile',
            'subOrders.items.services.hubWorkItem',
            'subOrders.shipmentLegs.deliveryConfirmation',
            'subOrders.fulfillmentIncidents',
            'items',
            'reservations',
            'settlementAllocations',
            'events',
        ]);
    }

    private function reserveCoupon(CheckoutQuote $quote): void
    {
        if ($quote->coupon_id === null) {
            return;
        }

        $coupon = Coupon::query()
            ->lockForUpdate()
            ->findOrFail($quote->coupon_id);

        if (
            ! $coupon->is_active
            || ($coupon->starts_at !== null && $coupon->starts_at->isFuture())
            || ($coupon->ends_at !== null && $coupon->ends_at->isPast())
            || (
                $coupon->max_redemptions !== null
                && $coupon->redemption_count >= $coupon->max_redemptions
            )
        ) {
            throw new ApiDomainException(
                'checkout.coupon_unavailable',
                'کد تخفیف Quote دیگر قابل استفاده نیست.',
                409,
            );
        }

        if ($coupon->roastery_id !== null) {
            if ($quote->groups->count() !== 1 || $quote->groups->first()?->roastery_id !== $coupon->roastery_id) {
                throw new ApiDomainException(
                    'checkout.coupon_scope_invalid',
                    'محدوده کد تخفیف با گروه‌های سفارش سازگار نیست.',
                    409,
                );
            }
        }

        $coupon->increment('redemption_count');
    }

    private function assertQuoteTotals(CheckoutQuote $quote): void
    {
        $subtotal = $quote->groups->sum('subtotal');
        $shipping = $quote->groups->sum('shipping_total');
        $discount = $quote->groups->sum('discount_total');
        $tax = $quote->groups->sum('tax_total');
        $commission = $quote->groups->sum('commission_total');
        $grand = $quote->groups->sum('grand_total');

        if (
            $subtotal !== $quote->subtotal
            || $shipping !== $quote->shipping_total
            || $discount !== $quote->discount_total
            || $tax !== $quote->tax_total
            || $commission !== $quote->commission_total
            || $grand !== $quote->grand_total
        ) {
            throw new ApiDomainException(
                'mixed_state_conflict',
                'مبالغ گروه‌های Quote با مبلغ نهایی سازگار نیست.',
                409,
            );
        }

        foreach ($quote->groups as $group) {
            $itemSubtotal = $group->items->sum('line_total');
            $packagingTotal = 0;
            $grindingTotal = 0;
            $itemTax = 0;
            $itemCommission = 0;
            foreach ($group->items as $quoteItem) {
                $itemTax += $quoteItem->tax_amount;
                $itemCommission += $quoteItem->commission_amount;
                $hasHubGrinding = $quoteItem->services->contains(
                    static fn ($service): bool => $service->service_type === 'grinding'
                        && $service->provider_type === 'rosta_hub',
                );
                $hasFreeHubPackaging = false;

                foreach ($quoteItem->services as $service) {
                    $packagingTotal += $service->packaging_fee;
                    if ($service->service_type === 'grinding') {
                        $grindingTotal += $service->service_fee;
                    }
                    $itemTax += $service->tax_amount;
                    $itemCommission += $service->commission_amount;
                    if (
                        $service->service_type === 'packaging'
                        && $service->provider_type === 'rosta_hub'
                        && $service->packaging_fee === 0
                        && $service->total_amount === 0
                    ) {
                        $hasFreeHubPackaging = true;
                    }
                    if (
                        $hasHubGrinding
                        && $service->service_type === 'packaging'
                        && $service->provider_type === 'roastery'
                        && $service->packaging_fee !== 0
                    ) {
                        throw new ApiDomainException(
                            'mixed_state_conflict',
                            'بسته‌بندی روستری در مسیر هاب باید صفر باشد.',
                            409,
                        );
                    }
                }

                if ($hasHubGrinding && ! $hasFreeHubPackaging) {
                    throw new ApiDomainException(
                        'mixed_state_conflict',
                        'خط بسته‌بندی رایگان هاب در Quote وجود ندارد.',
                        409,
                    );
                }
            }

            $hubRoute = is_array($group->pricing_snapshot)
                ? ($group->pricing_snapshot['hub_route'] ?? null)
                : null;
            if (is_array($hubRoute)) {
                $routeTotal = (int) ($hubRoute['route']['total_shipping_fee'] ?? -1);
                if ($routeTotal !== $group->shipping_total) {
                    throw new ApiDomainException(
                        'mixed_state_conflict',
                        'جمع مسیر ارسال هاب با Quote سازگار نیست.',
                        409,
                    );
                }
            }

            $shippingFinancial = is_array($group->financial_snapshot)
                ? ($group->financial_snapshot['components']['shipping'] ?? [])
                : [];
            $itemTax += (int) ($shippingFinancial['tax_amount'] ?? 0);
            $itemCommission += (int) ($shippingFinancial['commission_amount'] ?? 0);
            $expectedPayable = is_array($group->financial_snapshot)
                ? array_sum(array_map(
                    static fn (array $component): int => ($component['owner_type'] ?? null) === 'roastery'
                        ? (int) ($component['payable_amount'] ?? 0)
                        : 0,
                    $group->financial_snapshot['components'] ?? [],
                ))
                : $group->grand_total;

            $expectedGrand = $group->subtotal
                + $group->packaging_total
                + $group->grinding_total
                + $group->shipping_total
                + $group->tax_total
                - $group->discount_total;

            if (
                $itemSubtotal !== $group->subtotal
                || $packagingTotal !== $group->packaging_total
                || $grindingTotal !== $group->grinding_total
                || $itemTax !== $group->tax_total
                || $itemCommission !== $group->commission_total
                || $expectedPayable !== $group->payable_total
                || $expectedGrand !== $group->grand_total
            ) {
                throw new ApiDomainException(
                    'mixed_state_conflict',
                    'یکی از گروه‌های Quote دارای جمع ناسازگار است.',
                    409,
                );
            }
        }
    }

    /**
     * @param  array<string, int>  $bases
     * @return array<string, int>
     */
    private function allocateMoney(int $amount, array $bases): array
    {
        if ($amount === 0) {
            return array_fill_keys(array_keys($bases), 0);
        }

        $remainingAmount = $amount;
        $remainingBase = array_sum($bases);
        $allocations = [];
        $lastKey = array_key_last($bases);

        foreach ($bases as $key => $base) {
            if ($key === $lastKey) {
                $allocations[$key] = $remainingAmount;
                break;
            }

            $share = $this->multiplyDivideFloor($remainingAmount, $base, $remainingBase);
            $allocations[$key] = $share;
            $remainingAmount -= $share;
            $remainingBase -= $base;
        }

        return $allocations;
    }

    private function multiplyDivideFloor(int $left, int $right, int $divisor): int
    {
        if ($left < 0 || $right < 0 || $divisor <= 0) {
            throw new LogicException('Money ratio operands are invalid.');
        }

        $partWhole = intdiv($left, $divisor);
        $partRemainder = $left % $divisor;
        $totalWhole = 0;
        $totalRemainder = 0;
        $multiplier = $right;

        while ($multiplier > 0) {
            if (($multiplier & 1) === 1) {
                $totalWhole += $partWhole;
                $totalRemainder += $partRemainder;
                if ($totalRemainder >= $divisor) {
                    $totalWhole++;
                    $totalRemainder -= $divisor;
                }
            }

            $multiplier = intdiv($multiplier, 2);
            if ($multiplier === 0) {
                break;
            }

            $partWhole *= 2;
            $partRemainder *= 2;
            if ($partRemainder >= $divisor) {
                $partWhole++;
                $partRemainder -= $divisor;
            }
        }

        return $totalWhole;
    }

    /** @param array<string, mixed> $snapshot */
    private function recordTaxLine(
        SettlementAllocation $allocation,
        Order $order,
        SubOrder $subOrder,
        ?OrderItem $orderItem,
        ?OrderItemService $orderItemService,
        string $component,
        array $snapshot,
    ): void {
        if ($allocation->tax_amount <= 0) {
            return;
        }

        OrderTaxLine::query()->create([
            'settlement_allocation_id' => $allocation->id,
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'order_item_id' => $orderItem?->id,
            'order_item_service_id' => $orderItemService?->id,
            'component' => $component,
            'tax_code' => (string) ($snapshot['tax_rule']['code'] ?? 'policy-defined'),
            'jurisdiction' => (string) ($snapshot['tax_rule']['jurisdiction'] ?? 'policy-defined'),
            'taxable_amount' => (int) ($snapshot['taxable_amount'] ?? 0),
            'tax_amount' => $allocation->tax_amount,
            'rate_basis_points' => isset($snapshot['tax_rule']['rate_basis_points'])
                ? (int) $snapshot['tax_rule']['rate_basis_points']
                : null,
            'policy_version' => isset($snapshot['policy_snapshot']['tax']['version'])
                ? (int) $snapshot['policy_snapshot']['tax']['version']
                : null,
            'calculation_snapshot' => $snapshot,
        ]);
    }

    /** @param array<string, mixed> $snapshot */
    private function recordCommissionAllocation(
        SettlementAllocation $source,
        Order $order,
        SubOrder $subOrder,
        ?OrderItem $orderItem,
        ?OrderItemService $orderItemService,
        int $commission,
        array $snapshot,
    ): void {
        if ($commission <= 0) {
            return;
        }

        SettlementAllocation::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'order_item_id' => $orderItem?->id,
            'order_item_service_id' => $orderItemService?->id,
            'allocation_type' => 'commission',
            'owner_type' => 'rosta',
            'owner_id' => null,
            'status' => SettlementAllocationStatus::Held,
            'gross_amount' => $commission,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'commission_amount' => 0,
            'net_amount' => $commission,
            'currency' => $source->currency,
            'pricing_version' => 'ps4a-financial-truth-v1',
            'source_reference' => 'settlement_allocation:'.$source->id.':commission',
            'idempotency_key' => $source->idempotency_key.':commission',
            'metadata' => [
                'source_allocation_id' => $source->id,
                'commission_rule' => $snapshot['commission_rule'] ?? null,
            ],
            'financial_snapshot' => [
                ...$snapshot,
                'allocation_role' => 'platform_commission',
                'source_allocation_id' => $source->id,
            ],
        ]);
    }

    private function appendEvent(
        Order $order,
        User $user,
        Request $request,
        string $type,
        string $title,
        string $nextState,
        ?SubOrder $subOrder = null,
        ?OrderItem $orderItem = null,
        ?OrderItemService $orderItemService = null,
    ): void {
        $occurredAt = now();

        OrderEvent::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder?->id,
            'order_item_id' => $orderItem?->id,
            'order_item_service_id' => $orderItemService?->id,
            'event_type' => $type,
            'previous_state' => null,
            'next_state' => $nextState,
            'actor_type' => 'customer',
            'actor_user_id' => $user->id,
            'request_id' => $request->headers->get('X-Request-ID'),
            'customer_title' => $title,
            'customer_description' => null,
            'internal_metadata' => [
                'quote_id' => $order->quote_id,
                'service_type' => $orderItemService?->service_type,
            ],
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
        ]);
    }
}
