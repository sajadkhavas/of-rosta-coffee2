from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace(relative: str, old: str, new: str, expected: int = 1) -> None:
    path = ROOT / relative
    text = path.read_text(encoding="utf-8")
    count = text.count(old)
    if count != expected:
        raise SystemExit(f"{relative}: expected {expected} matches, found {count}: {old[:100]!r}")
    path.write_text(text.replace(old, new), encoding="utf-8")


# Backend request contracts.
for request_file in [
    "backend/app/Http/Requests/Checkout/CartValidateRequest.php",
    "backend/app/Http/Requests/Checkout/CheckoutQuoteRequest.php",
]:
    replace(
        request_file,
        "            ['variant_id', 'quantity'],",
        "            ['variant_id', 'quantity', 'grinding_profile_id'],",
    )
    replace(
        request_file,
        "            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],",
        "            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],\n"
        "            'items.*.grinding_profile_id' => ['nullable', 'string', 'max:200'],",
    )

# Quote service: authoritative selection, pricing and immutable snapshots.
quote = "backend/app/Services/Checkout/QuoteService.php"
replace(quote, "use App\\Models\\CheckoutQuoteItemService;\n", "use App\\Models\\CheckoutQuoteItemService;\nuse App\\Models\\GrindingProfile;\n")
replace(
    quote,
    "        private readonly ProductPackagingPolicy $packaging,\n    ) {}",
    "        private readonly ProductPackagingPolicy $packaging,\n"
    "        private readonly RoasteryGrindingSelection $grinding,\n"
    "    ) {}",
)
replace(
    quote,
    "     * @param  list<array{variant_id: string, quantity: int}>  $items",
    "     * @param  list<array{variant_id: string, quantity: int, grinding_profile_id?: string|null}>  $items",
    expected=3,
)
replace(
    quote,
    "                'quantity' => (int) $item['quantity'],\n            ])",
    "                'quantity' => (int) $item['quantity'],\n"
    "                'grinding_profile_id' => isset($item['grinding_profile_id'])\n"
    "                    ? (string) $item['grinding_profile_id']\n"
    "                    : null,\n"
    "            ])",
)
replace(
    quote,
    "                'product.roastery.cover',\n",
    "                'product.roastery.cover',\n"
    "                'product.roastery.grindingCapability.profiles',\n",
)
replace(
    quote,
    "         *     packaging_total: int,\n"
    "         *     items: list<array{\n"
    "         *         product: Product,\n"
    "         *         variant: ProductVariant,\n"
    "         *         batch: RoastBatch|null,\n"
    "         *         quantity: int,\n"
    "         *         line_total: int,\n"
    "         *         unit_packaging_fee: int,\n"
    "         *         line_packaging_total: int\n"
    "         *     }>\n",
    "         *     packaging_total: int,\n"
    "         *     grinding_total: int,\n"
    "         *     items: list<array{\n"
    "         *         product: Product,\n"
    "         *         variant: ProductVariant,\n"
    "         *         batch: RoastBatch|null,\n"
    "         *         quantity: int,\n"
    "         *         line_total: int,\n"
    "         *         unit_packaging_fee: int,\n"
    "         *         line_packaging_total: int,\n"
    "         *         line_grinding_total: int,\n"
    "         *         grinding: array{\n"
    "         *             profile: GrindingProfile,\n"
    "         *             unit_fee: int,\n"
    "         *             line_total: int,\n"
    "         *             pricing_snapshot: array<string, mixed>,\n"
    "         *             service_snapshot: array<string, mixed>\n"
    "         *         }|null\n"
    "         *     }>\n",
)
replace(
    quote,
    "            $linePackagingTotal = $this->multiplyMoney($unitPackagingFee, $quantity);\n"
    "            $subtotal = $this->addMoney($subtotal, $lineTotal);",
    "            $linePackagingTotal = $this->multiplyMoney($unitPackagingFee, $quantity);\n"
    "            $grinding = $this->grinding->resolve(\n"
    "                $product->roastery,\n"
    "                $variant,\n"
    "                $item['grinding_profile_id'],\n"
    "                $quantity,\n"
    "            );\n"
    "            $lineGrindingTotal = $grinding['line_total'] ?? 0;\n"
    "            $subtotal = $this->addMoney($subtotal, $lineTotal);",
)
replace(
    quote,
    "                    'packaging_total' => 0,\n                    'items' => [],",
    "                    'packaging_total' => 0,\n"
    "                    'grinding_total' => 0,\n"
    "                    'items' => [],",
)
replace(
    quote,
    "            $resolvedGroups[$roasteryId]['packaging_total'] = $this->addMoney(\n"
    "                $resolvedGroups[$roasteryId]['packaging_total'],\n"
    "                $linePackagingTotal,\n"
    "            );\n"
    "            $resolvedGroups[$roasteryId]['items'][] = [",
    "            $resolvedGroups[$roasteryId]['packaging_total'] = $this->addMoney(\n"
    "                $resolvedGroups[$roasteryId]['packaging_total'],\n"
    "                $linePackagingTotal,\n"
    "            );\n"
    "            $resolvedGroups[$roasteryId]['grinding_total'] = $this->addMoney(\n"
    "                $resolvedGroups[$roasteryId]['grinding_total'],\n"
    "                $lineGrindingTotal,\n"
    "            );\n"
    "            $resolvedGroups[$roasteryId]['items'][] = [",
)
replace(
    quote,
    "                'line_packaging_total' => $linePackagingTotal,\n            ];",
    "                'line_packaging_total' => $linePackagingTotal,\n"
    "                'line_grinding_total' => $lineGrindingTotal,\n"
    "                'grinding' => $grinding,\n"
    "            ];",
)
replace(
    quote,
    "            $group['grand_total'] = $this->addMoney(\n"
    "                $this->addMoney(\n"
    "                    $group['subtotal'] - $group['discount_total'],\n"
    "                    $group['packaging_total'],\n"
    "                ),\n"
    "                $group['shipping_total'],\n"
    "            );",
    "            $group['grand_total'] = $this->addMoney(\n"
    "                $this->addMoney(\n"
    "                    $this->addMoney(\n"
    "                        $group['subtotal'] - $group['discount_total'],\n"
    "                        $group['packaging_total'],\n"
    "                    ),\n"
    "                    $group['grinding_total'],\n"
    "                ),\n"
    "                $group['shipping_total'],\n"
    "            );",
)
replace(quote, "                    'grinding_total' => 0,", "                    'grinding_total' => $group['grinding_total'],")
replace(quote, "                        'version' => 'r5d-product-packaging-v1',", "                        'version' => 'r5f-roastery-grinding-v1',", expected=1)
replace(
    quote,
    "                        'service_snapshot' => $packagingSnapshot,\n                    ]);",
    "                        'service_snapshot' => $packagingSnapshot,\n"
    "                    ]);\n\n"
    "                    $grinding = $resolved['grinding'];\n"
    "                    if ($grinding !== null) {\n"
    "                        CheckoutQuoteItemService::query()->create([\n"
    "                            'quote_group_id' => $quoteGroup->id,\n"
    "                            'quote_item_id' => $quoteItem->id,\n"
    "                            'service_type' => 'grinding',\n"
    "                            'provider_type' => 'roastery',\n"
    "                            'provider_roastery_id' => $product->roastery_id,\n"
    "                            'grinding_profile_id' => $grinding['profile']->id,\n"
    "                            'service_fee' => $resolved['line_grinding_total'],\n"
    "                            'packaging_fee' => 0,\n"
    "                            'shipping_fee' => 0,\n"
    "                            'tax_amount' => 0,\n"
    "                            'total_amount' => $resolved['line_grinding_total'],\n"
    "                            'currency' => 'IRR',\n"
    "                            'pricing_snapshot' => $grinding['pricing_snapshot'],\n"
    "                            'service_snapshot' => $grinding['service_snapshot'],\n"
    "                        ]);\n"
    "                    }",
)

# Order creation: lock/revalidate service, allocate money and append event.
order = "backend/app/Services/Checkout/OrderService.php"
replace(
    order,
    "        private readonly AuditRecorder $audit,\n    ) {}",
    "        private readonly AuditRecorder $audit,\n"
    "        private readonly RoasteryGrindingSelection $grinding,\n"
    "    ) {}",
)
replace(
    order,
    "                    foreach ($quoteItem->services as $quoteService) {\n"
    "                        $orderService = OrderItemService::query()->create([",
    "                    foreach ($quoteItem->services as $quoteService) {\n"
    "                        if ($quoteService->service_type === 'grinding') {\n"
    "                            $this->grinding->assertQuoteServiceOrderable(\n"
    "                                $quoteService,\n"
    "                                $variant,\n"
    "                                $quoteItem->quantity,\n"
    "                            );\n"
    "                        }\n\n"
    "                        $orderService = OrderItemService::query()->create([",
)
replace(
    order,
    "                        }\n                    }\n                }\n\n                $shipmentLeg = ShipmentLeg::query()->create([",
    "                        }\n\n"
    "                        if ($orderService->service_type === 'grinding') {\n"
    "                            if ($orderService->service_fee > 0) {\n"
    "                                SettlementAllocation::query()->create([\n"
    "                                    'order_id' => $order->id,\n"
    "                                    'sub_order_id' => $subOrder->id,\n"
    "                                    'order_item_id' => $orderItem->id,\n"
    "                                    'order_item_service_id' => $orderService->id,\n"
    "                                    'allocation_type' => 'grinding',\n"
    "                                    'owner_type' => 'roastery',\n"
    "                                    'owner_id' => $group->roastery_id,\n"
    "                                    'status' => SettlementAllocationStatus::Held,\n"
    "                                    'gross_amount' => $orderService->service_fee,\n"
    "                                    'discount_amount' => 0,\n"
    "                                    'tax_amount' => $orderService->tax_amount,\n"
    "                                    'net_amount' => $orderService->total_amount,\n"
    "                                    'currency' => $orderService->currency,\n"
    "                                    'pricing_version' => 'r5f-roastery-grinding-v1',\n"
    "                                    'source_reference' => 'quote_service:'.$quoteService->id,\n"
    "                                    'idempotency_key' => 'order:'.$order->id.':service:'.$orderService->id.':grinding',\n"
    "                                    'metadata' => [\n"
    "                                        'quote_id' => $quote->id,\n"
    "                                        'quote_group_id' => $group->id,\n"
    "                                        'quote_item_service_id' => $quoteService->id,\n"
    "                                    ],\n"
    "                                ]);\n"
    "                            }\n\n"
    "                            $this->appendEvent(\n"
    "                                order: $order,\n"
    "                                user: $user,\n"
    "                                request: $request,\n"
    "                                type: 'grinding.requested',\n"
    "                                title: 'سرویس آسیاب ثبت شد',\n"
    "                                nextState: OrderItemServiceStatus::Requested->value,\n"
    "                                subOrder: $subOrder,\n"
    "                                orderItem: $orderItem,\n"
    "                                orderItemService: $orderService,\n"
    "                            );\n"
    "                        }\n"
    "                    }\n"
    "                }\n\n"
    "                $shipmentLeg = ShipmentLeg::query()->create([",
)
replace(
    order,
    "            $itemSubtotal = $group->items->sum('line_total');\n"
    "            $expectedGrand = $group->subtotal\n"
    "                + $group->packaging_total\n"
    "                + $group->grinding_total\n"
    "                + $group->shipping_total\n"
    "                + $group->tax_total\n"
    "                - $group->discount_total;\n\n"
    "            if ($itemSubtotal !== $group->subtotal || $expectedGrand !== $group->grand_total) {",
    "            $itemSubtotal = $group->items->sum('line_total');\n"
    "            $packagingTotal = 0;\n"
    "            $grindingTotal = 0;\n"
    "            foreach ($group->items as $quoteItem) {\n"
    "                foreach ($quoteItem->services as $service) {\n"
    "                    $packagingTotal += $service->packaging_fee;\n"
    "                    if ($service->service_type === 'grinding') {\n"
    "                        $grindingTotal += $service->service_fee;\n"
    "                    }\n"
    "                }\n"
    "            }\n"
    "            $expectedGrand = $group->subtotal\n"
    "                + $group->packaging_total\n"
    "                + $group->grinding_total\n"
    "                + $group->shipping_total\n"
    "                + $group->tax_total\n"
    "                - $group->discount_total;\n\n"
    "            if (\n"
    "                $itemSubtotal !== $group->subtotal\n"
    "                || $packagingTotal !== $group->packaging_total\n"
    "                || $grindingTotal !== $group->grinding_total\n"
    "                || $expectedGrand !== $group->grand_total\n"
    "            ) {",
)
replace(
    order,
    "        string $nextState,\n        ?SubOrder $subOrder = null,\n    ): void {",
    "        string $nextState,\n"
    "        ?SubOrder $subOrder = null,\n"
    "        ?OrderItem $orderItem = null,\n"
    "        ?OrderItemService $orderItemService = null,\n"
    "    ): void {",
)
replace(
    order,
    "            'sub_order_id' => $subOrder?->id,\n            'event_type' => $type,",
    "            'sub_order_id' => $subOrder?->id,\n"
    "            'order_item_id' => $orderItem?->id,\n"
    "            'order_item_service_id' => $orderItemService?->id,\n"
    "            'event_type' => $type,",
)
replace(
    order,
    "            'internal_metadata' => [\n                'quote_id' => $order->quote_id,\n            ],",
    "            'internal_metadata' => [\n"
    "                'quote_id' => $order->quote_id,\n"
    "                'service_type' => $orderItemService?->service_type,\n"
    "            ],",
)

# Customer resources.
quote_resource = "backend/app/Http/Resources/CheckoutQuoteResource.php"
replace(
    quote_resource,
    "                            'provider_type' => $service->provider_type,\n"
    "                            'packaging_fee' => $service->packaging_fee,",
    "                            'provider_type' => $service->provider_type,\n"
    "                            'grinding_profile' => $service->grinding_profile_id === null\n"
    "                                ? null\n"
    "                                : [\n"
    "                                    'id' => $service->service_snapshot['profile']['id'],\n"
    "                                    'code' => $service->service_snapshot['profile']['code'],\n"
    "                                    'version' => $service->service_snapshot['profile']['version'],\n"
    "                                    'name' => $service->service_snapshot['profile']['name'],\n"
    "                                    'brew_method' => $service->service_snapshot['profile']['brew_method'],\n"
    "                                ],\n"
    "                            'service_fee' => $service->service_fee,\n"
    "                            'packaging_fee' => $service->packaging_fee,",
)
replace(
    quote_resource,
    "            'packaging_total' => $this->groups->sum('packaging_total'),\n            'shipping_total' => $this->shipping_total,",
    "            'packaging_total' => $this->groups->sum('packaging_total'),\n"
    "            'grinding_total' => $this->groups->sum('grinding_total'),\n"
    "            'shipping_total' => $this->shipping_total,",
)
order_resource = "backend/app/Http/Resources/OrderResource.php"
replace(
    order_resource,
    "            'packaging_total' => $this->subOrders->sum('packaging_total'),\n            'shipping_total' => $this->shipping_total,",
    "            'packaging_total' => $this->subOrders->sum('packaging_total'),\n"
    "            'grinding_total' => $this->subOrders->sum('grinding_total'),\n"
    "            'shipping_total' => $this->shipping_total,",
)
replace(
    order_resource,
    "                            'name' => $service->grindingProfile->public_name,\n                        ],",
    "                            'name' => $service->grindingProfile->public_name,\n"
    "                            'brew_method' => $service->grindingProfile->brew_method,\n"
    "                        ],",
)

# Frontend cart storage and context.
cart_storage = "src/lib/cart-storage.ts"
replace(cart_storage, 'export const CART_STORAGE_KEY = "rosta_cart_v4";', 'export const CART_STORAGE_KEY = "rosta_cart_v5";')
replace(
    cart_storage,
    'export const LEGACY_CART_STORAGE_KEYS = ["rosta_cart", "rosta_cart_v2", "rosta_cart_v3"] as const;',
    'export const LEGACY_CART_STORAGE_KEYS = [\n  "rosta_cart",\n  "rosta_cart_v2",\n  "rosta_cart_v3",\n  "rosta_cart_v4",\n] as const;',
)
replace(cart_storage, "export const CART_STORAGE_VERSION = 4 as const;", "export const CART_STORAGE_VERSION = 5 as const;")
replace(
    cart_storage,
    "    packagingFeeAmount: z.number().int().nonnegative().max(Number.MAX_SAFE_INTEGER),\n    quantity:",
    "    packagingFeeAmount: z.number().int().nonnegative().max(Number.MAX_SAFE_INTEGER),\n"
    "    grindingProfileId: idSchema.nullable(),\n"
    "    quantity:",
)
replace(
    cart_storage,
    "      packagingFeeAmount:\n        candidate.packagingFeeMode === \"fixed\"\n          ? Math.max(0, Number(candidate.packagingFeeAmount ?? 0))\n          : 0,\n      addedAt:",
    "      packagingFeeAmount:\n"
    "        candidate.packagingFeeMode === \"fixed\"\n"
    "          ? Math.max(0, Number(candidate.packagingFeeAmount ?? 0))\n"
    "          : 0,\n"
    "      grindingProfileId:\n"
    "        typeof candidate.grindingProfileId === \"string\" ? candidate.grindingProfileId : null,\n"
    "      addedAt:",
)
replace(
    cart_storage,
    "    packagingFeeAmount: product.packaging.feeAmount,\n    quantity:",
    "    packagingFeeAmount: product.packaging.feeAmount,\n"
    "    grindingProfileId: null,\n"
    "    quantity:",
)

cart_context = "src/lib/cart-context.tsx"
replace(
    cart_context,
    "  updateQuantity: (variantId: string, quantity: number) => void;\n  clear:",
    "  updateQuantity: (variantId: string, quantity: number) => void;\n"
    "  setGrindingProfile: (variantId: string, profileId: string | null) => void;\n"
    "  clear:",
)
replace(
    cart_context,
    "  const clear = useCallback(() => setItems([]), []);",
    "  const setGrindingProfile = useCallback((variantId: string, profileId: string | null) => {\n"
    "    setItems((current) =>\n"
    "      safelyNormalize(\n"
    "        current.map((item) =>\n"
    "          item.variantId === variantId ? { ...item, grindingProfileId: profileId } : item,\n"
    "        ),\n"
    "      ),\n"
    "    );\n"
    "  }, []);\n\n"
    "  const clear = useCallback(() => setItems([]), []);",
)
replace(
    cart_context,
    "        quantity: item.quantity,\n      })),",
    "        quantity: item.quantity,\n"
    "        grindingProfileId: item.grindingProfileId,\n"
    "      })),",
)
replace(
    cart_context,
    "      updateQuantity,\n      clear,",
    "      updateQuantity,\n"
    "      setGrindingProfile,\n"
    "      clear,",
)
replace(
    cart_context,
    "      updateQuantity,\n      clear,\n      itemCount,",
    "      updateQuantity,\n"
    "      setGrindingProfile,\n"
    "      clear,\n"
    "      itemCount,",
)

# Frontend API contracts and strict parsers.
checkout_api = "src/lib/api/checkout.ts"
replace(
    checkout_api,
    "  quantity: number;\n}",
    "  quantity: number;\n  grindingProfileId?: string | null;\n}",
    expected=1,
)
replace(
    checkout_api,
    "        providerType: service.provider_type,\n        packagingFee:",
    "        providerType: service.provider_type,\n"
    "        grindingProfile: service.grinding_profile\n"
    "          ? {\n"
    "              id: service.grinding_profile.id,\n"
    "              code: service.grinding_profile.code,\n"
    "              version: service.grinding_profile.version,\n"
    "              name: service.grinding_profile.name,\n"
    "              brewMethod: service.grinding_profile.brew_method,\n"
    "            }\n"
    "          : null,\n"
    "        serviceFee: service.service_fee,\n"
    "        packagingFee:",
)
replace(
    checkout_api,
    "    packagingTotal: value.packaging_total,\n    shippingTotal:",
    "    packagingTotal: value.packaging_total,\n"
    "    grindingTotal: value.grinding_total,\n"
    "    shippingTotal:",
)
replace(checkout_api, "function itemsPayload(items: CartApiItem[]) {", "export function buildCartItemsPayload(items: CartApiItem[]) {")
replace(
    checkout_api,
    "    uniqueVariants.add(variantId);\n    return { variant_id: variantId, quantity: item.quantity };",
    "    const grindingProfileId = item.grindingProfileId?.trim() || null;\n"
    "    if (grindingProfileId && grindingProfileId.length > 200) {\n"
    "      throw new Error(\"شناسه پروفایل آسیاب معتبر نیست.\");\n"
    "    }\n"
    "    uniqueVariants.add(variantId);\n"
    "    return {\n"
    "      variant_id: variantId,\n"
    "      quantity: item.quantity,\n"
    "      grinding_profile_id: grindingProfileId,\n"
    "    };",
)
replace(checkout_api, "itemsPayload(items)", "buildCartItemsPayload(items)")
replace(checkout_api, "itemsPayload(input.items)", "buildCartItemsPayload(input.items)")

contracts = "src/lib/api/contracts.ts"
replace(
    contracts,
    "  providerType: string;\n  packagingFee:",
    "  providerType: string;\n"
    "  grindingProfile?: {\n"
    "    id: string;\n"
    "    code: string;\n"
    "    version: number;\n"
    "    name: string;\n"
    "    brewMethod: string;\n"
    "  } | null;\n"
    "  serviceFee: number;\n"
    "  packagingFee:",
    expected=1,
)
replace(
    contracts,
    "  packagingTotal: number;\n  shippingTotal: number;\n  discountTotal:",
    "  packagingTotal: number;\n"
    "  grindingTotal: number;\n"
    "  shippingTotal: number;\n"
    "  discountTotal:",
    expected=2,
)
replace(
    contracts,
    "  status: string;\n  serviceFee:",
    "  status: string;\n"
    "  grindingProfile?: {\n"
    "    id: string;\n"
    "    code: string;\n"
    "    version: number;\n"
    "    name: string;\n"
    "    brewMethod: string;\n"
    "  } | null;\n"
    "  serviceFee:",
)

schemas = "src/lib/api/schemas.ts"
replace(
    schemas,
    "const commerceServiceWireSchema = z\n",
    "const grindingProfileSelectionWireSchema = z\n"
    "  .object({\n"
    "    id: identifierSchema,\n"
    "    code: boundedText(100),\n"
    "    version: z.number().int().min(1).max(65_535),\n"
    "    name: boundedText(160),\n"
    "    brew_method: boundedText(100),\n"
    "  })\n"
    "  .strict();\n\n"
    "const commerceServiceWireSchema = z\n",
)
replace(
    schemas,
    "    provider_type: boundedText(80),\n    packaging_fee:",
    "    provider_type: boundedText(80),\n"
    "    grinding_profile: grindingProfileSelectionWireSchema.nullable(),\n"
    "    service_fee: moneySchema,\n"
    "    packaging_fee:",
    expected=1,
)
replace(
    schemas,
    "    packaging_total: moneySchema,\n    shipping_total:",
    "    packaging_total: moneySchema,\n"
    "    grinding_total: moneySchema,\n"
    "    shipping_total:",
    expected=1,
)
replace(
    schemas,
    "      const shipping = group.shipping_total ?? group.shipping_cost ?? 0;",
    "      const grinding = group.items.reduce(\n"
    "        (sum, item) =>\n"
    "          sum +\n"
    "          item.services\n"
    "            .filter((service) => service.type === \"grinding\")\n"
    "            .reduce((serviceSum, service) => serviceSum + service.service_fee, 0),\n"
    "        0,\n"
    "      );\n"
    "      const shipping = group.shipping_total ?? group.shipping_cost ?? 0;",
)
replace(
    schemas,
    "      if (expectedGrand !== group.grand_total) {",
    "      if (grinding !== group.grinding_total) {\n"
    "        context.addIssue({\n"
    "          code: z.ZodIssueCode.custom,\n"
    "          path: [\"groups\", groupIndex, \"grinding_total\"],\n"
    "          message: \"جمع آسیاب گروه Quote ناسازگار است.\",\n"
    "        });\n"
    "      }\n"
    "      if (expectedGrand !== group.grand_total) {",
)
replace(
    schemas,
    "    const shippingTotal = value.groups.reduce(\n",
    "    const grindingTotal = value.groups.reduce((sum, group) => sum + group.grinding_total, 0);\n"
    "    const shippingTotal = value.groups.reduce(\n",
)
replace(
    schemas,
    "      packagingTotal !== value.packaging_total ||\n      shippingTotal:",
    "      packagingTotal !== value.packaging_total ||\n"
    "      grindingTotal !== value.grinding_total ||\n"
    "      shippingTotal:",
)
# The previous replacement target contains an equality expression, not a field separator.
replace(
    schemas,
    "      packagingTotal !== value.packaging_total ||\n      shippingTotal !== value.shipping_total ||",
    "      packagingTotal !== value.packaging_total ||\n"
    "      grindingTotal !== value.grinding_total ||\n"
    "      shippingTotal !== value.shipping_total ||",
)
replace(
    schemas,
    "    grinding_profile: z.unknown().nullable().optional(),",
    "    grinding_profile: grindingProfileSelectionWireSchema.nullable(),",
)
replace(
    schemas,
    "    packaging_total: moneySchema,\n    shipping_total: moneySchema,\n    discount_total:",
    "    packaging_total: moneySchema,\n"
    "    grinding_total: moneySchema,\n"
    "    shipping_total: moneySchema,\n"
    "    discount_total:",
    expected=1,
)

financial = "src/lib/api/financial-contracts.ts"
replace(
    financial,
    "    const expectedGrand =\n",
    "    const grindingTotal = subOrder.items.reduce(\n"
    "      (sum, item) =>\n"
    "        sum +\n"
    "        item.services\n"
    "          .filter((service) => service.type === \"grinding\")\n"
    "          .reduce((serviceSum, service) => serviceSum + service.service_fee, 0),\n"
    "      0,\n"
    "    );\n"
    "    const expectedGrand =\n",
)
replace(
    financial,
    "    if (expectedGrand !== subOrder.grand_total) {",
    "    if (grindingTotal !== subOrder.grinding_total) {\n"
    "      addIssue(\n"
    "        context,\n"
    "        [\"sub_orders\", subOrderIndex, \"grinding_total\"],\n"
    "        \"جمع آسیاب زیرسفارش ناسازگار است.\",\n"
    "      );\n"
    "    }\n"
    "    if (expectedGrand !== subOrder.grand_total) {",
)
replace(
    financial,
    "    const shipping = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.shipping_total, 0);",
    "    const grinding = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.grinding_total, 0);\n"
    "    const shipping = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.shipping_total, 0);",
)
replace(
    financial,
    "      packaging !== value.packaging_total ||\n      shipping !== value.shipping_total ||",
    "      packaging !== value.packaging_total ||\n"
    "      grinding !== value.grinding_total ||\n"
    "      shipping !== value.shipping_total ||",
)

orders_api = "src/lib/api/orders.ts"
replace(
    orders_api,
    "      providerType: service.provider_type,\n      status: service.status,",
    "      providerType: service.provider_type,\n"
    "      status: service.status,\n"
    "      grindingProfile: service.grinding_profile\n"
    "        ? {\n"
    "            id: service.grinding_profile.id,\n"
    "            code: service.grinding_profile.code,\n"
    "            version: service.grinding_profile.version,\n"
    "            name: service.grinding_profile.name,\n"
    "            brewMethod: service.grinding_profile.brew_method,\n"
    "          }\n"
    "        : null,",
)
replace(
    orders_api,
    "    packagingTotal: value.packaging_total,\n    shippingTotal:",
    "    packagingTotal: value.packaging_total,\n"
    "    grindingTotal: value.grinding_total,\n"
    "    shippingTotal:",
)

# Customer surfaces.
cart_route = "src/routes/cart.tsx"
replace(cart_route, 'import { Breadcrumb } from "@/components/Breadcrumb";\n', 'import { Breadcrumb } from "@/components/Breadcrumb";\nimport { CartGrindingSelector } from "@/components/cart/CartGrindingSelector";\n')
replace(
    cart_route,
    "    updateQuantity,\n    removeItem,",
    "    updateQuantity,\n"
    "    setGrindingProfile,\n"
    "    removeItem,",
)
replace(
    cart_route,
    "                    </div>\n                  </div>\n                </li>",
    "                    </div>\n"
    "                    <CartGrindingSelector\n"
    "                      item={item}\n"
    "                      onChange={(profileId) => setGrindingProfile(item.variantId, profileId)}\n"
    "                    />\n"
    "                  </div>\n"
    "                </li>",
)
replace(
    cart_route,
    "                <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                  <dt>ارسال</dt>",
    "                <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                  <dt>آسیاب روستری</dt>\n"
    "                  <dd className=\"font-mono\">\n"
    "                    {quote.grindingTotal === 0 ? \"—\" : formatIrr(quote.grindingTotal)}\n"
    "                  </dd>\n"
    "                </div>\n"
    "                <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                  <dt>ارسال</dt>",
)

checkout_route = "src/routes/checkout.tsx"
replace(
    checkout_route,
    "                   <p className=\"mt-1 text-[10px] text-[color:var(--light)]\">\n"
    "                     {item.packagingFeeAmount === 0\n"
    "                       ? \"بسته‌بندی رایگان\"\n"
    "                       : `بسته‌بندی ${formatIrr(item.packagingFeeAmount * item.quantity)}`}\n"
    "                   </p>",
    "                   <p className=\"mt-1 text-[10px] text-[color:var(--light)]\">\n"
    "                     {item.packagingFeeAmount === 0\n"
    "                       ? \"بسته‌بندی رایگان\"\n"
    "                       : `بسته‌بندی ${formatIrr(item.packagingFeeAmount * item.quantity)}`}\n"
    "                   </p>\n"
    "                   {quote?.groups\n"
    "                     .flatMap((group) => group.items)\n"
    "                     .find((line) => line.variant.id === item.variantId)\n"
    "                     ?.services.filter((service) => service.type === \"grinding\")\n"
    "                     .map((service) => (\n"
    "                       <p key={service.id} className=\"mt-1 text-[10px] text-[color:var(--roast)]\">\n"
    "                         {service.label ?? service.grindingProfile?.name ?? \"آسیاب روستری\"} ·{\" \"}\n"
    "                         {service.isFree ? \"رایگان\" : formatIrr(service.serviceFee)}\n"
    "                       </p>\n"
    "                     ))}",
)
replace(
    checkout_route,
    "                <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                  <dt>ارسال</dt>",
    "                <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                  <dt>آسیاب روستری</dt>\n"
    "                  <dd className=\"font-mono\">\n"
    "                    {quote.grindingTotal === 0 ? \"—\" : formatIrr(quote.grindingTotal)}\n"
    "                  </dd>\n"
    "                </div>\n"
    "                <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                  <dt>ارسال</dt>",
)

order_route = "src/routes/orders.$id.tsx"
replace(
    order_route,
    "                         <p className=\"mt-2 font-mono text-sm font-bold text-[color:var(--roast)]\">\n"
    "                           {formatIrr(item.lineTotal)}\n"
    "                         </p>",
    "                         <p className=\"mt-2 font-mono text-sm font-bold text-[color:var(--roast)]\">\n"
    "                           {formatIrr(item.lineTotal)}\n"
    "                         </p>\n"
    "                         {item.services.length ? (\n"
    "                           <ul className=\"mt-3 space-y-1 text-[11px] text-[color:var(--light)]\">\n"
    "                             {item.services.map((service) => (\n"
    "                               <li key={service.id} className=\"flex flex-wrap justify-between gap-2\">\n"
    "                                 <span>\n"
    "                                   {service.type === \"grinding\"\n"
    "                                     ? service.grindingProfile?.name ?? service.label ?? \"آسیاب روستری\"\n"
    "                                     : service.label ?? \"بسته‌بندی روستری\"}\n"
    "                                 </span>\n"
    "                                 <span>{service.isFree ? \"رایگان\" : formatIrr(service.totalAmount)}</span>\n"
    "                               </li>\n"
    "                             ))}\n"
    "                           </ul>\n"
    "                         ) : null}",
)
replace(
    order_route,
    "                 <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                   <dt>ارسال</dt>\n"
    "                   <dd>{formatIrr(subOrder.shippingTotal)}</dd>\n"
    "                 </div>",
    "                 <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                   <dt>بسته‌بندی</dt>\n"
    "                   <dd>{subOrder.packagingTotal === 0 ? \"رایگان\" : formatIrr(subOrder.packagingTotal)}</dd>\n"
    "                 </div>\n"
    "                 <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                   <dt>آسیاب</dt>\n"
    "                   <dd>{subOrder.grindingTotal === 0 ? \"—\" : formatIrr(subOrder.grindingTotal)}</dd>\n"
    "                 </div>\n"
    "                 <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "                   <dt>ارسال</dt>\n"
    "                   <dd>{formatIrr(subOrder.shippingTotal)}</dd>\n"
    "                 </div>",
)
replace(
    order_route,
    "             <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "               <dt>ارسال</dt>",
    "             <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "               <dt>بسته‌بندی</dt>\n"
    "               <dd>{order.packagingTotal === 0 ? \"رایگان\" : formatIrr(order.packagingTotal)}</dd>\n"
    "             </div>\n"
    "             <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "               <dt>آسیاب</dt>\n"
    "               <dd>{order.grindingTotal === 0 ? \"—\" : formatIrr(order.grindingTotal)}</dd>\n"
    "             </div>\n"
    "             <div className=\"flex justify-between text-[color:var(--light)]\">\n"
    "               <dt>ارسال</dt>",
)

# Permanent gates.
replace(
    "package.json",
    '    "audit:r5e": "node scripts/audit-r5e-grinding-capability.mjs"\n',
    '    "audit:r5e": "node scripts/audit-r5e-grinding-capability.mjs",\n'
    '    "audit:r5f": "node scripts/audit-r5f-roastery-grinding.mjs"\n',
)
replace(
    "package.json",
    "bun run audit:r5e && bun run test:unit",
    "bun run audit:r5e && bun run audit:r5f && bun run test:unit",
)
replace(
    "backend/composer.json",
    '    "audit:r5e": "@php scripts/audit-r5e-grinding-capability.php"\n',
    '    "audit:r5e": "@php scripts/audit-r5e-grinding-capability.php",\n'
    '    "audit:r5f": "@php scripts/audit-r5f-roastery-grinding.php"\n',
)
replace(
    "backend/composer.json",
    '      "@audit:r5e",\n      "@php artisan test",',
    '      "@audit:r5e",\n      "@audit:r5f",\n      "@php artisan test",',
)

print("R5F deterministic product patch applied")
