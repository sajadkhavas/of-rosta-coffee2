<?php

namespace App\Services\Checkout;

use App\Enums\IdempotencyStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\QuotePurpose;
use App\Enums\ReservationStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\ApiDomainException;
use App\Models\CheckoutQuote;
use App\Models\Coupon;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderIdempotencyKey;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrderService
{
    public function __construct(
        private readonly CheckoutHasher $hasher,
        private readonly AuditRecorder $audit,
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
                'expires_at' => now()->addHours(24),
            ]);

            $quote = CheckoutQuote::query()
                ->with('items')
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

            if ($quote->items->isEmpty()) {
                throw new ApiDomainException(
                    'checkout.quote_empty',
                    'Quote فاقد آیتم معتبر است.',
                    409,
                );
            }

            $variantIds = $quote->items
                ->pluck('variant_id')
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

            foreach ($quote->items as $quoteItem) {
                /** @var ProductVariant|null $variant */
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
                    || $product->roastery_id !== $quote->roastery_id
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

            if ($quote->coupon_id !== null) {
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

                $coupon->increment('redemption_count');
            }

            $order = new Order([
                'user_id' => $user->id,
                'roastery_id' => $quote->roastery_id,
                'quote_id' => $quote->id,
                'status' => OrderStatus::AwaitingPayment,
                'address_snapshot' => $quote->address_snapshot,
                'notes' => $notes,
                'subtotal' => $quote->subtotal,
                'shipping_total' => $quote->shipping_total,
                'discount_total' => $quote->discount_total,
                'grand_total' => $quote->grand_total,
                'currency' => $quote->currency,
                'placed_at' => now(),
            ]);
            $order->id = (string) Str::ulid();
            $order->order_number = 'R-'.now()->format('ymd').'-'.strtoupper(substr($order->id, -8));
            $order->save();

            $subOrder = SubOrder::query()->create([
                'order_id' => $order->id,
                'roastery_id' => $quote->roastery_id,
                'status' => SubOrderStatus::PendingAcceptance,
                'subtotal' => $quote->subtotal,
                'shipping_total' => $quote->shipping_total,
            ]);

            $reservationExpiresAt = now()->addMinutes(
                (int) config('rosta.checkout.reservation_ttl_minutes', 20),
            );

            foreach ($quote->items as $quoteItem) {
                /** @var ProductVariant $variant */
                $variant = $variants->get($quoteItem->variant_id);

                $orderItem = OrderItem::query()->create([
                    'order_id' => $order->id,
                    'sub_order_id' => $subOrder->id,
                    'product_id' => $quoteItem->product_id,
                    'variant_id' => $quoteItem->variant_id,
                    'roast_batch_id' => $quoteItem->roast_batch_id,
                    'quantity' => $quoteItem->quantity,
                    'unit_price' => $quoteItem->unit_price,
                    'line_total' => $quoteItem->line_total,
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
            }

            $quote->forceFill(['consumed_at' => now()])->save();

            $record->forceFill([
                'status' => IdempotencyStatus::Completed,
                'order_id' => $order->id,
            ])->save();

            $this->audit->record(
                'checkout.order.created',
                actor: $user,
                auditable: $order,
                metadata: [
                    'quote_id' => $quote->id,
                    'roastery_id' => $quote->roastery_id,
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
            'subOrder.roastery.logo',
            'subOrder.roastery.cover',
            'subOrder.items',
            'items',
            'reservations',
        ]);
    }
}
