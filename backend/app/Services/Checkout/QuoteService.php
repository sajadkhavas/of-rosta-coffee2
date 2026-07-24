<?php

namespace App\Services\Checkout;

use App\Enums\ProductStatus;
use App\Enums\QuotePurpose;
use App\Exceptions\ApiDomainException;
use App\Models\Address;
use App\Models\CheckoutQuote;
use App\Models\CheckoutQuoteItem;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\User;
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
    ) {}

    /**
     * @param  list<array{variant_id: string, quantity: int}>  $items
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
     * @param  list<array{variant_id: string, quantity: int}>  $items
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
     * @param  list<array{variant_id: string, quantity: int}>  $items
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
                'product.variants' => static fn ($query) => $query->where('is_active', true)->orderBy('weight_grams'),
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

        $roastery = null;
        $subtotal = 0;
        $resolvedItems = [];

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

            if ($roastery === null) {
                $roastery = $product->roastery;
            } elseif ($roastery->id !== $product->roastery_id) {
                throw new ApiDomainException(
                    'cart.multiple_roasteries',
                    'هر سفارش باید فقط شامل محصولات یک روستری باشد.',
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

            $lineTotal = $this->multiplyMoney($variant->price, $quantity);
            $subtotal = $this->addMoney($subtotal, $lineTotal);

            $resolvedItems[] = [
                'product' => $product,
                'variant' => $variant,
                'batch' => $product->latestRoastBatch,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        if (! $roastery instanceof Roastery) {
            throw new ApiDomainException(
                'cart.empty',
                'سبد خرید خالی است.',
                422,
            );
        }

        $couponResult = $purpose === QuotePurpose::Checkout
            ? $this->coupons->resolve($couponCode, $roastery, $subtotal)
            : ['coupon' => null, 'discount' => 0];

        $shippingResult = $purpose === QuotePurpose::Checkout
            ? $this->shipping->quote($roastery, $address, $subtotal)
            : ['total' => 0, 'snapshot' => null];

        $shippingTotal = (int) $shippingResult['total'];
        $beforeDiscount = $this->addMoney($subtotal, $shippingTotal);
        $discountTotal = min(
            $beforeDiscount,
            (int) $couponResult['discount'],
        );
        $grandTotal = $beforeDiscount - $discountTotal;

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
            $roastery,
            $couponResult,
            $payload,
            $subtotal,
            $shippingTotal,
            $discountTotal,
            $grandTotal,
            $shippingResult,
            $resolvedItems,
        ): CheckoutQuote {
            $quote = CheckoutQuote::query()->create([
                'user_id' => $user?->id,
                'address_id' => $address?->id,
                'roastery_id' => $roastery->id,
                'coupon_id' => $couponResult['coupon']?->id,
                'purpose' => $purpose,
                'payload_hash' => $this->hasher->hash($payload),
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'currency' => 'IRR',
                'address_snapshot' => $address === null
                    ? null
                    : $this->addressSnapshot($address),
                'shipping_snapshot' => $shippingResult['snapshot'],
                'warnings' => [],
                'expires_at' => now()->addMinutes(
                    (int) config('rosta.checkout.quote_ttl_minutes', 15),
                ),
            ]);

            foreach ($resolvedItems as $resolved) {
                /** @var Product $product */
                $product = $resolved['product'];
                /** @var ProductVariant $variant */
                $variant = $resolved['variant'];
                /** @var RoastBatch|null $batch */
                $batch = $resolved['batch'];

                CheckoutQuoteItem::query()->create([
                    'quote_id' => $quote->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'roast_batch_id' => $batch?->id,
                    'quantity' => $resolved['quantity'],
                    'unit_price' => $variant->price,
                    'compare_at_price' => $variant->compare_at_price,
                    'line_total' => $resolved['line_total'],
                    'product_snapshot' => $this->productSnapshot($product),
                    'variant_snapshot' => $this->variantSnapshot($variant),
                    'roast_batch_snapshot' => $batch === null
                        ? null
                        : $this->batchSnapshot($batch),
                ]);
            }

            return $quote->load([
                'roastery.logo',
                'roastery.cover',
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
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    private function batchSnapshot(RoastBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'roasted_at' => $batch->roasted_at->toIso8601String(),
            'available_from' => $batch->available_from?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
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
