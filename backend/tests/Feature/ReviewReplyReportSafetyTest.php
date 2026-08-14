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
use App\Models\ReviewReply;
use App\Models\ReviewReplyRevision;
use App\Models\ReviewReport;
use App\Models\Roastery;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class ReviewReplyReportSafetyTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_reply_is_owned_canonical_sanitized_audited_and_cannot_bypass_moderation(): void
    {
        [$customer, $roastery, , $item] = $this->fixture('reply');
        $this->authenticateWithRole($customer, Role::Customer);
        $review = $this->postJson('/api/v1/reviews', ['order_item_id' => $item->id, 'rating' => 5, 'title' => '<b>عنوان</b>', 'body' => '<script>alert(1)</script>قهوه خوب بود.'])
            ->assertCreated()->json('data');
        $this->assertStringNotContainsString('<script>', (string) $review['body']);

        $admin = User::factory()->create();
        $this->authenticateWithRole($admin, Role::Administrator);
        $this->patchJson('/api/v1/admin/reviews/'.$review['id'], ['status' => 'approved'])->assertOk();

        $foreignOwner = User::factory()->create();
        $foreignRoastery = Roastery::query()->create(['name' => 'foreign', 'slug' => 'foreign-review', 'description' => '', 'status' => 'verified', 'verified_at' => now()]);
        $this->authenticateWithRole($foreignOwner, Role::RoasteryOwner, 'roastery', $foreignRoastery->id);
        $this->putJson('/api/v1/seller/roasteries/'.$roastery->id.'/reviews/'.$review['id'].'/reply', ['body' => 'غیرمجاز'])->assertNotFound();

        $owner = User::factory()->create();
        $this->authenticateWithRole($owner, Role::RoasteryOwner, 'roastery', $roastery->id);
        $reply = $this->putJson('/api/v1/seller/roasteries/'.$roastery->id.'/reviews/'.$review['id'].'/reply', ['body' => '<img src=x onerror=alert(1)>پاسخ فروشنده'])
            ->assertOk()->assertJsonPath('data.status', 'visible')->json('data');
        $this->assertSame('پاسخ فروشنده', $reply['body']);
        $this->putJson('/api/v1/seller/roasteries/'.$roastery->id.'/reviews/'.$review['id'].'/reply', ['body' => 'نسخه دوم'])->assertOk();
        $this->assertDatabaseCount('review_replies', 1);
        $this->assertDatabaseCount('review_reply_revisions', 1);
        $this->assertSame('پاسخ فروشنده', ReviewReplyRevision::query()->firstOrFail()->body);

        $this->authenticateWithRole($admin, Role::Administrator);
        $this->patchJson('/api/v1/admin/review-replies/'.$reply['id'], ['status' => 'hidden', 'reason' => 'نیاز به بررسی'])->assertOk()->assertJsonPath('data.status', 'hidden');
        $this->authenticateWithRole($owner, Role::RoasteryOwner, 'roastery', $roastery->id);
        $this->putJson('/api/v1/seller/roasteries/'.$roastery->id.'/reviews/'.$review['id'].'/reply', ['body' => 'ویرایش بعد از moderation'])->assertOk()->assertJsonPath('data.status', 'hidden');

        $this->getJson('/api/v1/products/review-product-reply/reviews')->assertOk()->assertJsonPath('data.items.0.seller_reply', null);
    }

    public function test_report_is_duplicate_safe_minimized_rate_scoped_and_admin_only(): void
    {
        [$customer, , , $item] = $this->fixture('report');
        $this->authenticateWithRole($customer, Role::Customer);
        $review = $this->postJson('/api/v1/reviews', ['order_item_id' => $item->id, 'rating' => 4, 'body' => 'نظر قابل گزارش'])->assertCreated()->json('data');
        $admin = User::factory()->create();
        $this->authenticateWithRole($admin, Role::Administrator);
        $this->patchJson('/api/v1/admin/reviews/'.$review['id'], ['status' => 'approved'])->assertOk();

        $reporter = User::factory()->create();
        $this->authenticateWithRole($reporter, Role::Customer);
        $payload = ['reason' => 'personal_data', 'evidence' => '<b>فقط توضیح ضروری</b>'];
        $first = $this->postJson('/api/v1/reviews/'.$review['id'].'/reports', $payload)
            ->assertCreated()->assertHeader('Cache-Control', 'private, no-store, max-age=0')->json('data');
        $second = $this->postJson('/api/v1/reviews/'.$review['id'].'/reports', $payload)->assertCreated()->json('data');
        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('review_reports', 1);
        $this->assertSame('فقط توضیح ضروری', ReviewReport::query()->firstOrFail()->evidence);

        $this->getJson('/api/v1/admin/review-reports')->assertForbidden();
        $this->authenticateWithRole($admin, Role::Administrator);
        $this->getJson('/api/v1/admin/review-reports?status=open')->assertOk()->assertJsonPath('data.items.0.id', $first['id']);
        $this->patchJson('/api/v1/admin/review-reports/'.$first['id'], ['status' => 'resolved', 'resolution_reason' => 'بررسی شد'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertNotNull(ReviewReport::query()->firstOrFail()->moderated_at);
    }

    /** @return array{User, Roastery, Product, OrderItem} */
    private function fixture(string $suffix): array
    {
        $customer = User::factory()->create(['name' => 'سجاد خواص']);
        $roastery = Roastery::query()->create(['name' => 'Review '.$suffix, 'slug' => 'review-roastery-'.$suffix, 'description' => '', 'status' => 'verified', 'verified_at' => now()]);
        $origin = Origin::query()->create(['name' => 'Origin '.$suffix, 'slug' => 'review-origin-'.$suffix, 'country_code' => 'ETH']);
        $product = Product::query()->create(['roastery_id' => $roastery->id, 'origin_id' => $origin->id, 'name' => 'Review Product '.$suffix, 'slug' => 'review-product-'.$suffix, 'description' => '', 'processing_method' => 'washed', 'roast_level' => 'light', 'arabica_percentage' => 100, 'tasting_notes' => ['گل'], 'brewing_suggestions' => [], 'status' => 'published', 'published_at' => now()]);
        $variant = ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'SAFE-'.strtoupper($suffix), 'weight_grams' => 250, 'price' => 2_000_000, 'currency' => 'IRR', 'is_active' => true, 'stock_on_hand' => 5, 'stock_reserved' => 0]);
        $quote = CheckoutQuote::query()->create(['user_id' => $customer->id, 'address_id' => null, 'roastery_id' => $roastery->id, 'coupon_id' => null, 'purpose' => 'checkout', 'payload_hash' => hash('sha256', 'safety-'.$suffix), 'subtotal' => 2_000_000, 'shipping_total' => 0, 'discount_total' => 0, 'grand_total' => 2_000_000, 'currency' => 'IRR', 'address_snapshot' => ['recipient_name' => 'سجاد', 'recipient_mobile' => '09123456789', 'province' => 'البرز', 'city' => 'کرج', 'address_line' => 'نشانی تست', 'postal_code' => '1234567890'], 'shipping_snapshot' => [], 'warnings' => [], 'expires_at' => now()->addMinutes(15), 'consumed_at' => now()]);
        $order = Order::query()->create(['user_id' => $customer->id, 'roastery_id' => $roastery->id, 'quote_id' => $quote->id, 'order_number' => 'R-SAFE-'.strtoupper($suffix), 'status' => OrderStatus::Delivered, 'address_snapshot' => $quote->address_snapshot, 'subtotal' => 2_000_000, 'shipping_total' => 0, 'discount_total' => 0, 'grand_total' => 2_000_000, 'currency' => 'IRR', 'placed_at' => now()->subDay(), 'paid_at' => now()->subDay()]);
        $subOrder = SubOrder::query()->create(['order_id' => $order->id, 'roastery_id' => $roastery->id, 'status' => 'delivered', 'subtotal' => 2_000_000, 'shipping_total' => 0, 'delivered_at' => now()]);
        $item = OrderItem::query()->create(['order_id' => $order->id, 'sub_order_id' => $subOrder->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'roast_batch_id' => null, 'quantity' => 1, 'unit_price' => 2_000_000, 'line_total' => 2_000_000, 'product_snapshot' => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'primary_image' => null], 'variant_snapshot' => ['id' => $variant->id, 'sku' => $variant->sku, 'weight_grams' => 250, 'price' => 2_000_000, 'currency' => 'IRR'], 'roast_batch_snapshot' => null]);
        return [$customer, $roastery, $product, $item];
    }
}
