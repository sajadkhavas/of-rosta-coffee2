<?php

namespace App\Services\Checkout;

use App\Enums\ProductStatus;
use App\Enums\QuotePurpose;
use App\Exceptions\ApiDomainException;
use App\Models\Address;
use App\Models\CheckoutQuote;
use App\Models\CheckoutQuoteGroup;
use App\Models\CheckoutQuoteItem;
use App\Models\CheckoutQuoteItemService;
use App\Models\GrindingProfile;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\User;
use App\Services\B2B\WholesalePricingService;
use App\Services\Catalog\ProductPackagingPolicy;
use App\Services\Finance\FinancialTruthEngine;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class QuoteService
{
    private const int MAX_MONEY = 9_007_199_254_740_991;

    public function __construct(
        private readonly CheckoutHasher $hasher,
        private readonly CouponService $coupons,
        private readonly ShippingQuoteService $shipping,
        private readonly ProductPackagingPolicy $packaging,
        private readonly RoasteryGrindingSelection $grinding,
        private readonly FinancialTruthEngine $financialTruth,
        private readonly WholesalePricingService $wholesalePricing,
    ) {}

    /**
     * @param  list<array{variant_id: string, quantity: int, grinding_profile_id?: string|null}>  $items
     */
    public function validateCart(array $items): CheckoutQuote
    {
        return $this->create(
            QuotePurpose::CartValidation,
            null,
            null,
            $items,
            null,
        );
    }

    /**
     * @param  list<array{variant_id: string, quantity: int, grinding_profile_id?: string|null}>  $items
     */
    public function createCheckoutQuote(
        User $user,
        Address $address,
        array $items,
        ?string $couponCode,
    ): CheckoutQuote {
        if ($address->user_id !== $user->id) {
            abort(404);
        }

        return $this->create(
            QuotePurpose::Checkout,
            $user,
            $address,
            $items,
            $couponCode,
        );
    }

    /**
     * @param  list<array{variant_id: string, quantity: int, grinding_profile_id?: string|null}>  $items
     */
    private function create(
        QuotePurpose $purpose,
        ?User $user,
        ?Address $address,
        array $items,
        ?string $couponCode,
    ): CheckoutQuote {
        if ($purpose === QuotePurpose::Checkout && ! $address instanceof Address) {
            throw new LogicException('Checkout quotes require an address.');
        }

        $normalizedItems = collect($items)
            ->map(static fn (array $item): array => [
                'variant_id' => (string) $item['variant_id'],
                'quantity' => (int) $item['quantity'],
                'grinding_profile_id' => isset($item['grinding_profile_id'])
                    ? (string) $item['grinding_profile_id']
                    : null,
            ])
            ->sortBy('variant_id')
            ->values();

        /** @var Collection<string, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->with([
                'product.origin',
                'product.primaryImage',
                'product.latestRoastBatch',
                'product.roastery.logo',
                'product.roastery.cover',
                'product.roastery.grindingCapability.profiles',
                'product.variants' => static fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('weight_grams'),
                'wholesaleTiers' => static fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('min_weight_grams'),
            ])
            ->whereIn('id', $normalizedItems->pluck('variant_id'))
            ->get()
            ->keyBy('id');

        if ($variants->count() !== $normalizedItems->count()) {
            throw new ApiDomainException(
                'cart.variant_unavailable',
                'یکی از گزینه‌های سبد دیگر در دسترس نیست.',
                409,
            );
        }

        /**
         * @var array<string, array{
         *     roastery: Roastery,
         *     subtotal: int,
         *     packaging_total: int,
         *     grinding_total: int,
         *     hub_route: array<string, mixed>|null,
         *     items: list<array{
         *         product: Product,
         *         variant: ProductVariant,
         *         batch: RoastBatch|null,
         *         quantity: int,
         *         line_total: int,
         *         unit_packaging_fee: int,
         *         line_packaging_total: int,
         *         line_grinding_total: int,
         *         grinding: array{
         *             profile: GrindingProfile,
         *             unit_fee: int,
         *             line_total: int,
         *             pricing_snapshot: array<string, mixed>,
         *             provider_type: 'roastery'|'rosta_hub',
         *             provider_roastery_id: string|null,
         *             provider_hub_id: string|null,
         *             route_shipping_total: int,
         *             service_snapshot: array<string, mixed>
         *         }|null
         *     }>
         * }> $resolvedGroups
         */
        $resolvedGroups = [];
        $subtotal = 0;

        foreach ($normalizedItems as $item) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($item['variant_id']);
            if (! $variant instanceof ProductVariant) {
                throw new ApiDomainException(
                    'cart.variant_unavailable',
                    'یکی از گزینه‌های سبد دیگر در دسترس نیست.',
                    409,
                );
            }

            $product = $variant->product;
            if (
                ! $variant->is_active
                || $product->status !== ProductStatus::Published
                || $product->published_at === null
                || ! $product->roastery->isPubliclyVisible()
            ) {
                throw new ApiDomainException(
                    'cart.product_unavailable',
                    'یکی از محصولات سبد دیگر قابل فروش نیست.',
                    409,
                );
            }

            $quantity = $item['quantity'];
            if ($variant->availableQuantity() < $quantity) {
                throw new ApiDomainException(
                    'cart.insufficient_stock',
                    'موجودی یکی از گزینه‌های سبد کافی نیست.',
                    409,
                    ['variant_id' => [$variant->id]],
                );
            }

            $pricing = $this->wholesalePricing->resolve($user, $variant, $quantity);
            $unitPrice = $pricing['unit_price'];
            $lineTotal = $this->multiplyMoney($unitPrice, $quantity);
            $grinding = $this->grinding->resolve(
                $product->roastery,
                $variant,
                $item['grinding_profile_id'],
                $quantity,
                $address,
                $purpose === QuotePurpose::Checkout,
            );
            $isHubGrinding = ($grinding['provider_type'] ?? null) === 'rosta_hub';
            $unitPackagingFee = $isHubGrinding ? 0 : $product->packagingFee();
            $linePackagingTotal = $this->multiplyMoney($unitPackagingFee, $quantity);
            $lineGrindingTotal = $grinding['line_total'] ?? 0;
            $subtotal = $this->addMoney($subtotal, $lineTotal);
            $roasteryId = (string) $product->roastery_id;

            if (! isset($resolvedGroups[$roasteryId])) {
                $resolvedGroups[$roasteryId] = [
                    'roastery' => $product->roastery,
                    'subtotal' => 0,
                    'packaging_total' => 0,
                    'grinding_total' => 0,
                    'hub_route' => null,
                    'items' => [],
                ];
            }

            $resolvedGroups[$roasteryId]['subtotal'] = $this->addMoney(
                $resolvedGroups[$roasteryId]['subtotal'],
                $lineTotal,
            );
            $resolvedGroups[$roasteryId]['packaging_total'] = $this->addMoney(
                $resolvedGroups[$roasteryId]['packaging_total'],
                $linePackagingTotal,
            );
            $resolvedGroups[$roasteryId]['grinding_total'] = $this->addMoney(
                $resolvedGroups[$roasteryId]['grinding_total'],
                $lineGrindingTotal,
            );
            if ($isHubGrinding) {
                $route = $grinding['service_snapshot']['route'];
                $hubRoute = [
                    'hub' => $grinding['service_snapshot']['hub'],
                    'zone' => $grinding['service_snapshot']['zone'],
                    'route' => $route,
                ];
                $existingRoute = $resolvedGroups[$roasteryId]['hub_route'];
                if ($existingRoute !== null && $existingRoute !== $hubRoute) {
                    throw new ApiDomainException(
                        'mixed_state_conflict',
                        'همه سرویس‌های هاب یک روستری باید از یک مسیر یکسان عبور کنند.',
                        409,
                    );
                }
                $resolvedGroups[$roasteryId]['hub_route'] = $hubRoute;
            }
            $resolvedGroups[$roasteryId]['items'][] = [
                'product' => $product,
                'variant' => $variant,
                'batch' => $product->latestRoastBatch,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'pricing_snapshot' => $pricing['snapshot'],
                'line_total' => $lineTotal,
                'unit_packaging_fee' => $unitPackagingFee,
                'line_packaging_total' => $linePackagingTotal,
                'line_grinding_total' => $lineGrindingTotal,
                'grinding' => $grinding,
            ];
        }

        if ($resolvedGroups === []) {
            throw new ApiDomainException(
                'cart.empty',
                'سبد خرید خالی است.',
                422,
            );
        }

        ksort($resolvedGroups);

        /** @var EloquentCollection<int, Roastery> $roasteries */
        $roasteries = new EloquentCollection(array_map(
            static fn (array $group): Roastery => $group['roastery'],
            array_values($resolvedGroups),
        ));

        $couponResult = $purpose === QuotePurpose::Checkout
            ? $this->coupons->resolveMarketplace($couponCode, $roasteries, $subtotal)
            : ['coupon' => null, 'discount' => 0];

        $groupSubtotals = [];
        $shippingTotal = 0;
        $shippingSnapshots = [];
        $warnings = [];

        foreach ($resolvedGroups as $roasteryId => &$group) {
            if ($group['hub_route'] !== null) {
                $route = $group['hub_route']['route'];
                $shippingResult = $purpose === QuotePurpose::Checkout
                    ? [
                        'total' => (int) $route['total_shipping_fee'],
                        'snapshot' => [
                            'version' => 'r5g-rosta-hub-route-v1',
                            'provider_type' => 'rosta_hub',
                            ...$group['hub_route'],
                        ],
                    ]
                    : ['total' => 0, 'snapshot' => null];

                if ($purpose === QuotePurpose::CartValidation) {
                    $warnings[] = [
                        'code' => 'hub_address_confirmation_required',
                        'message' => 'آسیاب هاب رستا پس از انتخاب آدرس تهران یا کرج نهایی می‌شود.',
                    ];
                }
            } else {
                $shippingResult = $purpose === QuotePurpose::Checkout
                    ? $this->shipping->quote($group['roastery'], $address, $group['subtotal'])
                    : ['total' => 0, 'snapshot' => null];
            }

            $group['shipping_total'] = (int) $shippingResult['total'];
            $group['shipping_snapshot'] = $shippingResult['snapshot'];
            $shippingTotal = $this->addMoney($shippingTotal, $group['shipping_total']);
            $groupSubtotals[$roasteryId] = $group['subtotal'];

            if ($purpose === QuotePurpose::Checkout) {
                $shippingSnapshots[] = [
                    'roastery_id' => $roasteryId,
                    'snapshot' => $shippingResult['snapshot'],
                ];
            }
        }
        unset($group);

        $discountTotal = min($subtotal, (int) $couponResult['discount']);
        $discounts = $this->allocateMoney($discountTotal, $groupSubtotals);
        $grandTotal = 0;
        $taxTotal = 0;
        $commissionTotal = 0;
        $financialSnapshots = [];
        $calculatedAt = now()->toImmutable();

        foreach ($resolvedGroups as $roasteryId => &$group) {
            $group['discount_total'] = $discounts[$roasteryId] ?? 0;
            $itemBases = [];
            foreach ($group['items'] as $index => $resolved) {
                $itemBases['product:'.$index] = $resolved['line_total'];
            }
            $itemDiscounts = $this->allocateMoney($group['discount_total'], $itemBases);
            $components = [];
            foreach ($group['items'] as $index => $resolved) {
                $productKey = 'product:'.$index;
                $components[] = [
                    'key' => $productKey,
                    'component' => 'product',
                    'owner_type' => 'roastery',
                    'gross_amount' => $resolved['line_total'],
                    'discount_amount' => $itemDiscounts[$productKey] ?? 0,
                    'attributes' => [
                        'roastery_id' => $roasteryId,
                        'product_id' => $resolved['product']->id,
                        'provider_type' => 'roastery',
                    ],
                ];
                $components[] = [
                    'key' => 'packaging:'.$index,
                    'component' => 'packaging',
                    'owner_type' => 'roastery',
                    'gross_amount' => $resolved['line_packaging_total'],
                    'attributes' => [
                        'roastery_id' => $roasteryId,
                        'product_id' => $resolved['product']->id,
                        'provider_type' => 'roastery',
                    ],
                ];
                if ($resolved['grinding'] !== null) {
                    $components[] = [
                        'key' => 'grinding:'.$index,
                        'component' => 'grinding',
                        'owner_type' => $resolved['grinding']['provider_type'] === 'rosta_hub' ? 'rosta' : 'roastery',
                        'gross_amount' => $resolved['line_grinding_total'],
                        'attributes' => [
                            'roastery_id' => $roasteryId,
                            'product_id' => $resolved['product']->id,
                            'provider_type' => $resolved['grinding']['provider_type'],
                        ],
                    ];
                }
            }
            $components[] = [
                'key' => 'shipping',
                'component' => 'shipping',
                'owner_type' => $group['hub_route'] === null ? 'roastery' : 'rosta',
                'gross_amount' => $group['shipping_total'],
                'attributes' => [
                    'roastery_id' => $roasteryId,
                    'provider_type' => $group['hub_route'] === null ? 'roastery' : 'rosta_hub',
                ],
            ];

            $financial = $this->financialTruth->calculate($components, $calculatedAt);
            foreach ($group['items'] as $index => &$resolved) {
                $resolved['financial'] = [
                    'product' => $financial['components']['product:'.$index],
                    'packaging' => $financial['components']['packaging:'.$index],
                    'grinding' => $financial['components']['grinding:'.$index] ?? null,
                ];
            }
            unset($resolved);
            $group['tax_total'] = $financial['totals']['tax'];
            $group['commission_total'] = $financial['totals']['commission'];
            $group['payable_total'] = array_sum(array_map(
                static fn (array $component): int => $component['owner_type'] === 'roastery'
                    ? (int) $component['payable_amount']
                    : 0,
                $financial['components'],
            ));
            $group['financial_snapshot'] = [
                ...$financial['snapshot'],
                'components' => $financial['components'],
            ];
            $group['grand_total'] = $this->addMoney(
                $this->addMoney(
                    $this->addMoney(
                        $group['subtotal'] - $group['discount_total'],
                        $group['packaging_total'],
                    ),
                    $group['grinding_total'],
                ),
                $group['shipping_total'],
            );
            $group['grand_total'] = $this->addMoney($group['grand_total'], $group['tax_total']);
            $grandTotal = $this->addMoney($grandTotal, $group['grand_total']);
            $taxTotal = $this->addMoney($taxTotal, $group['tax_total']);
            $commissionTotal = $this->addMoney($commissionTotal, $group['commission_total']);
            $financialSnapshots[] = [
                'roastery_id' => $roasteryId,
                'snapshot' => $group['financial_snapshot'],
            ];
        }
        unset($group);

        $payload = [
            'purpose' => $purpose->value,
            'user_id' => $user?->id,
            'address_id' => $address?->id,
            'coupon_code' => $couponResult['coupon']?->code,
            'items' => $normalizedItems->all(),
        ];

        return DB::transaction(function () use (
            $purpose,
            $user,
            $address,
            $couponResult,
            $payload,
            $subtotal,
            $shippingTotal,
            $discountTotal,
            $taxTotal,
            $commissionTotal,
            $grandTotal,
            $shippingSnapshots,
            $resolvedGroups,
            $warnings,
            $financialSnapshots,
        ): CheckoutQuote {
            $singleRoasteryId = count($resolvedGroups) === 1
                ? array_key_first($resolvedGroups)
                : null;

            $quote = CheckoutQuote::query()->create([
                'user_id' => $user?->id,
                'address_id' => $address?->id,
                'roastery_id' => $singleRoasteryId,
                'coupon_id' => $couponResult['coupon']?->id,
                'purpose' => $purpose,
                'payload_hash' => $this->hasher->hash($payload),
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'commission_total' => $commissionTotal,
                'grand_total' => $grandTotal,
                'currency' => 'IRR',
                'address_snapshot' => $address === null
                    ? null
                    : $this->addressSnapshot($address),
                'shipping_snapshot' => ['groups' => $shippingSnapshots],
                'warnings' => $warnings,
                'financial_snapshot' => [
                    'version' => 'ps4a-financial-truth-v1',
                    'groups' => $financialSnapshots,
                ],
                'expires_at' => now()->addMinutes(
                    (int) config('rosta.checkout.quote_ttl_minutes', 15),
                ),
            ]);

            foreach ($resolvedGroups as $group) {
                $quoteGroup = CheckoutQuoteGroup::query()->create([
                    'quote_id' => $quote->id,
                    'roastery_id' => $group['roastery']->id,
                    'subtotal' => $group['subtotal'],
                    'packaging_total' => $group['packaging_total'],
                    'grinding_total' => $group['grinding_total'],
                    'shipping_total' => $group['shipping_total'],
                    'discount_total' => $group['discount_total'],
                    'tax_total' => $group['tax_total'],
                    'commission_total' => $group['commission_total'],
                    'payable_total' => $group['payable_total'],
                    'grand_total' => $group['grand_total'],
                    'currency' => 'IRR',
                    'pricing_snapshot' => [
                        'version' => $group['hub_route'] === null
                            ? 'r5f-roastery-grinding-v1'
                            : 'r5g-rosta-hub-grinding-v1',
                        'shipping' => $group['shipping_snapshot'],
                        'hub_route' => $group['hub_route'],
                    ],
                    'financial_snapshot' => $group['financial_snapshot'],
                ]);

                foreach ($group['items'] as $resolved) {
                    $product = $resolved['product'];
                    $variant = $resolved['variant'];
                    $batch = $resolved['batch'];

                    $quoteItem = CheckoutQuoteItem::query()->create([
                        'quote_id' => $quote->id,
                        'group_id' => $quoteGroup->id,
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'roast_batch_id' => $batch?->id,
                        'quantity' => $resolved['quantity'],
                        'unit_price' => $resolved['unit_price'],
                        'compare_at_price' => $variant->compare_at_price,
                        'line_total' => $resolved['line_total'],
                        'discount_amount' => $resolved['financial']['product']['discount_amount'],
                        'tax_amount' => $resolved['financial']['product']['tax_amount'],
                        'commission_amount' => $resolved['financial']['product']['commission_amount'],
                        'net_amount' => $resolved['financial']['product']['payable_amount'],
                        'financial_snapshot' => $resolved['financial']['product'],
                        'product_snapshot' => $this->productSnapshot($product),
                        'variant_snapshot' => [
                            ...$this->variantSnapshot($variant),
                            'pricing' => $resolved['pricing_snapshot'],
                        ],
                        'roast_batch_snapshot' => $batch === null
                            ? null
                            : $this->batchSnapshot($batch),
                    ]);

                    $grinding = $resolved['grinding'];
                    $isHubGrinding = ($grinding['provider_type'] ?? null) === 'rosta_hub';
                    $packagingSnapshot = $this->packaging->snapshot($product);
                    if ($isHubGrinding) {
                        $packagingSnapshot = [
                            ...$packagingSnapshot,
                            'mode' => 'free',
                            'fee_amount' => 0,
                            'is_free' => true,
                            'label' => 'بسته‌بندی روستری در مسیر هاب: رایگان',
                            'overridden_by' => 'rosta_hub',
                        ];
                    }

                    CheckoutQuoteItemService::query()->create([
                        'quote_group_id' => $quoteGroup->id,
                        'quote_item_id' => $quoteItem->id,
                        'service_type' => 'packaging',
                        'provider_type' => 'roastery',
                        'provider_roastery_id' => $product->roastery_id,
                        'provider_hub_id' => null,
                        'grinding_profile_id' => null,
                        'service_fee' => 0,
                        'packaging_fee' => $resolved['line_packaging_total'],
                        'shipping_fee' => 0,
                        'tax_amount' => $resolved['financial']['packaging']['tax_amount'],
                        'commission_amount' => $resolved['financial']['packaging']['commission_amount'],
                        'total_amount' => $resolved['line_packaging_total'] + $resolved['financial']['packaging']['tax_amount'],
                        'currency' => 'IRR',
                        'pricing_snapshot' => [
                            'version' => $isHubGrinding
                                ? 'r5g-rosta-hub-packaging-override-v1'
                                : 'r5d-product-packaging-v1',
                            'unit_fee' => $resolved['unit_packaging_fee'],
                            'quantity' => $resolved['quantity'],
                            'line_total' => $resolved['line_packaging_total'],
                        ],
                        'service_snapshot' => $packagingSnapshot,
                        'financial_snapshot' => $resolved['financial']['packaging'],
                    ]);

                    if ($isHubGrinding) {
                        CheckoutQuoteItemService::query()->create([
                            'quote_group_id' => $quoteGroup->id,
                            'quote_item_id' => $quoteItem->id,
                            'service_type' => 'packaging',
                            'provider_type' => 'rosta_hub',
                            'provider_roastery_id' => null,
                            'provider_hub_id' => $grinding['provider_hub_id'],
                            'grinding_profile_id' => null,
                            'service_fee' => 0,
                            'packaging_fee' => 0,
                            'shipping_fee' => 0,
                            'tax_amount' => 0,
                            'commission_amount' => 0,
                            'total_amount' => 0,
                            'currency' => 'IRR',
                            'pricing_snapshot' => [
                                'version' => 'r5g-rosta-hub-packaging-v1',
                                'unit_fee' => 0,
                                'quantity' => $resolved['quantity'],
                                'line_total' => 0,
                            ],
                            'service_snapshot' => [
                                'version' => 'r5g-rosta-hub-packaging-v1',
                                'provider_type' => 'rosta_hub',
                                'provider_hub_id' => $grinding['provider_hub_id'],
                                'is_free' => true,
                                'label' => 'بسته‌بندی هاب رستا رایگان',
                            ],
                            'financial_snapshot' => [
                                'status' => $group['financial_snapshot']['status'],
                                'component' => 'packaging',
                                'gross_amount' => 0,
                                'tax_amount' => 0,
                                'commission_amount' => 0,
                                'payable_amount' => 0,
                            ],
                        ]);
                    }

                    if ($grinding !== null) {
                        CheckoutQuoteItemService::query()->create([
                            'quote_group_id' => $quoteGroup->id,
                            'quote_item_id' => $quoteItem->id,
                            'service_type' => 'grinding',
                            'provider_type' => $grinding['provider_type'],
                            'provider_roastery_id' => $grinding['provider_roastery_id'],
                            'provider_hub_id' => $grinding['provider_hub_id'],
                            'grinding_profile_id' => $grinding['profile']->id,
                            'service_fee' => $resolved['line_grinding_total'],
                            'packaging_fee' => 0,
                            'shipping_fee' => 0,
                            'tax_amount' => $resolved['financial']['grinding']['tax_amount'],
                            'commission_amount' => $resolved['financial']['grinding']['commission_amount'],
                            'total_amount' => $resolved['line_grinding_total'] + $resolved['financial']['grinding']['tax_amount'],
                            'currency' => 'IRR',
                            'pricing_snapshot' => $grinding['pricing_snapshot'],
                            'service_snapshot' => $grinding['service_snapshot'],
                            'financial_snapshot' => $resolved['financial']['grinding'],
                        ]);
                    }
                }
            }

            return $quote->load([
                'groups.roastery.logo',
                'groups.roastery.cover',
                'groups.items.services',
                'items',
            ]);
        });
    }

    private function multiplyMoney(int $amount, int $multiplier): int
    {
        if (
            $amount < 0
            || $multiplier < 0
            || ($multiplier > 0 && $amount > intdiv(self::MAX_MONEY, $multiplier))
        ) {
            throw new ApiDomainException(
                'checkout.amount_out_of_range',
                'مبلغ محاسبه‌شده خارج از محدوده مجاز است.',
                409,
            );
        }

        return $amount * $multiplier;
    }

    private function addMoney(int $left, int $right): int
    {
        if (
            $left < 0
            || $right < 0
            || $left > self::MAX_MONEY - $right
        ) {
            throw new ApiDomainException(
                'checkout.amount_out_of_range',
                'مبلغ محاسبه‌شده خارج از محدوده مجاز است.',
                409,
            );
        }

        return $left + $right;
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

    /** @return array<string, mixed> */
    private function addressSnapshot(Address $address): array
    {
        return [
            'id' => $address->id,
            'title' => $address->title,
            'recipient_name' => $address->recipient_name,
            'recipient_mobile' => $address->recipient_mobile,
            'province' => $address->province,
            'city' => $address->city,
            'address_line' => $address->address_line,
            'postal_code' => $address->postal_code,
            'is_default' => $address->is_default,
        ];
    }

    /** @return array<string, mixed> */
    private function productSnapshot(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'origin' => [
                'id' => $product->origin->id,
                'name' => $product->origin->name,
                'country_code' => $product->origin->country_code,
            ],
            'processing_method' => $product->processing_method->value,
            'roast_level' => $product->roast_level->value,
            'arabica_percentage' => $product->arabica_percentage,
            'tasting_notes' => array_values($product->tasting_notes ?? []),
            'packaging' => $this->packaging->snapshot($product),
            'primary_image' => $this->mediaSnapshot($product->primaryImage),
            'roastery' => [
                'id' => $product->roastery->id,
                'name' => $product->roastery->name,
                'slug' => $product->roastery->slug,
                'city' => $product->roastery->city,
                'is_verified' => true,
                'logo' => $this->mediaSnapshot($product->roastery->logo),
                'cover' => $this->mediaSnapshot($product->roastery->cover),
                'preparation_time' => $product->roastery->preparation_min_hours === null
                    || $product->roastery->preparation_max_hours === null
                        ? null
                        : [
                            'min_hours' => $product->roastery->preparation_min_hours,
                            'max_hours' => $product->roastery->preparation_max_hours,
                        ],
                'rating' => $product->roastery->rating_value === null
                    ? null
                    : [
                        'value' => (float) $product->roastery->rating_value,
                        'count' => (int) $product->roastery->rating_count,
                    ],
            ],
            'variants' => $product->variants
                ->map(fn (ProductVariant $variant): array => $this->variantSnapshot($variant))
                ->values()
                ->all(),
            'latest_roast_batch' => $product->latestRoastBatch === null
                ? null
                : $this->batchSnapshot($product->latestRoastBatch),
            'status' => ProductStatus::Published->value,
        ];
    }

    /** @return array<string, mixed> */
    private function variantSnapshot(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'weight_grams' => $variant->weight_grams,
            'price' => $variant->price,
            'compare_at_price' => $variant->compare_at_price,
            'currency' => $variant->currency,
            'is_available' => $variant->is_active && $variant->availableQuantity() > 0,
            'available_quantity' => $variant->availableQuantity(),
        ];
    }

    /** @return array<string, mixed> */
    private function batchSnapshot(RoastBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'roasted_at' => $batch->roasted_at->toIso8601String(),
            'available_from' => $batch->available_from?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function mediaSnapshot(?MediaAsset $media): ?array
    {
        if (! $media instanceof MediaAsset) {
            return null;
        }

        return [
            'id' => $media->id,
            'alt' => $media->alt,
            'width' => $media->width,
            'height' => $media->height,
            'blur_data_url' => $media->blur_data_url,
            'sources' => array_values($media->sources ?? []),
        ];
    }
}
