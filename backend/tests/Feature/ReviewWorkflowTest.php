<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CheckoutQuote;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class ReviewWorkflowTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_only_delivered_owner_can_submit_and_only_approved_review_is_public(): void
    {
        [$customer, $product, $item] = $this->fixture(OrderStatus::Delivered, 'approved');
        $this->authenticateWithRole($customer, Role::Customer);

        $created = $this->postJson('/api/v1/reviews', [
            'order_item_id' => $item->id,
            'rating' => 5,
            'title' => 'تازه و خوش‌عطر',
            'body' => 'قهوه بسیار تازه بود و تاریخ رست دقیق روی بسته درج شده بود.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_verified_purchase', true)
            ->json('data');

        $this->getJson("/api/v1/products/{$product->slug}/reviews")
            ->assertOk()
            ->assertJsonPath('data.summary.count', 0)
            ->assertJsonCount(0, 'data.items');

        $this->postJson('/api/v1/reviews', [
            'order_item_id' => $item->id,
            'rating' => 4,
            'body' => 'این ارسال تکراری نباید پذیرفته شود.',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'review.already_submitted');

        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);
        $this->patchJson('/api/v1/admin/reviews/'.$created['id'], [
            'status' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->getJson("/api/v1/products/{$product->slug}/reviews")
            ->assertOk()
            ->assertJsonPath('data.summary.count', 1)
            ->assertJsonPath('data.summary.average', 5)
            ->assertJsonPath('data.items.0.rating', 5)
            ->assertJsonPath('data.items.0.author', 'س***')
            ->assertJsonPath('data.items.0.is_verified_purchase', true);

        $foreignCustomer = User::factory()->create();
        $this->authenticateWithRole($foreignCustomer, Role::Customer);
        $this->postJson('/api/v1/reviews', [
            'order_item_id' => $item->id,
            'rating' => 3,
            'body' => 'کاربر دیگر نباید به این آیتم سفارش دسترسی داشته باشد.',
        ])->assertNotFound();
    }

    public function test_review_is_rejected_before_delivery(): void
    {
        [$customer, , $item] = $this->fixture(OrderStatus::Paid, 'undelivered');
        $this->authenticateWithRole($customer, Role::Customer);

        $this->postJson('/api/v1/reviews', [
            'order_item_id' => $item->id,
            'rating' => 5,
            'body' => 'این نظر پیش از تحویل نباید ثبت شود.',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'review.order_not_delivered');
    }

    /** @return array{User, Product, OrderItem} */
    private function fixture(OrderStatus $status, string $suffix): array
    {
        $customer = User::factory()->create(['name' => 'سجاد خواص']);
        $roastery = Roastery::query()->create([
            'name' => 'روستری نظر '.$suffix,
            'slug' => 'review-roastery-'.$suffix,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $origin = Origin::query()->create([
            'name' => 'اتیوپی نظر '.$suffix,
            'slug' => 'review-origin-'.$suffix,
            'country_code' => 'ETH',
        ]);
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'قهوه نظر '.$suffix,
            'slug' => 'review-product-'.$suffix,
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'light',
            'arabica_percentage' => 100,
            'tasting_notes' => ['گل'],
            'brewing_suggestions' => [],
            'status' => 'published',
            'published_at' => now(),
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'REVIEW-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
        ]);
        $quote = CheckoutQuote::query()->create([
            'user_id' => $customer->id,
            'address_id' => null,
            'roastery_id' => $roastery->id,
            'coupon_id' => null,
            'purpose' => 'checkout',
            'payload_hash' => hash('sha256', 'review-'.$suffix),
            'subtotal' => 2_000_000,
            'shipping_total' => 300_000,
            'discount_total' => 0,
            'grand_total' => 2_300_000,
            'currency' => 'IRR',
            'address_snapshot' => [
                'recipient_name' => 'سجاد خواص',
                'recipient_mobile' => '09123456789',
                'province' => 'البرز',
                'city' => 'کرج',
                'address_line' => 'نشانی تست',
                'postal_code' => '1234567890',
            ],
            'shipping_snapshot' => [],
            'warnings' => [],
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => now(),
        ]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'roastery_id' => $roastery->id,
            'quote_id' => $quote->id,
            'order_number' => 'R-REVIEW-'.strtoupper($suffix),
            'status' => $status,
            'address_snapshot' => $quote->address_snapshot,
            'subtotal' => 2_000_000,
            'shipping_total' => 300_000,
            'discount_total' => 0,
            'grand_total' => 2_300_000,
            'currency' => 'IRR',
            'placed_at' => now()->subDay(),
            'paid_at' => now()->subDay(),
        ]);
        $subOrder = SubOrder::query()->create([
            'order_id' => $order->id,
            'roastery_id' => $roastery->id,
            'status' => $status === OrderStatus::Delivered ? 'delivered' : 'pending_acceptance',
            'subtotal' => 2_000_000,
            'shipping_total' => 300_000,
            'delivered_at' => $status === OrderStatus::Delivered ? now() : null,
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'roast_batch_id' => null,
            'quantity' => 1,
            'unit_price' => 2_000_000,
            'line_total' => 2_000_000,
            'product_snapshot' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'primary_image' => null,
            ],
            'variant_snapshot' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'weight_grams' => 250,
                'price' => 2_000_000,
                'currency' => 'IRR',
            ],
            'roast_batch_snapshot' => null,
        ]);

        return [$customer, $product, $item];
    }
}
