<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CheckoutQuote;
use App\Models\InventoryRestock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\Shipment;
use App\Models\StockLedgerEntry;
use App\Models\SubOrder;
use App\Models\SubOrderStatusHistory;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class FulfillmentLifecycleTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rosta.notifications.enabled' => false,
            'rosta.notifications.sms_provider' => 'disabled',
        ]);
    }

    public function test_seller_must_follow_state_machine_and_tracking_is_required(): void
    {
        [$seller, $foreignSeller, $roastery, $order] = $this->fixture('flow');
        $this->authenticateWithRole($seller, Role::RoasteryOwner, 'roastery', $roastery->id);
        $endpoint = "/api/v1/seller/roasteries/{$roastery->id}/orders/{$order->id}/fulfillment";

        $this->patchJson($endpoint, ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.sub_orders.0.status', 'accepted');

        $this->patchJson($endpoint, [
            'status' => 'shipped',
            'carrier' => 'پست',
            'tracking_code' => 'TRACK-001',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'fulfillment.transition_invalid');

        $this->patchJson($endpoint, ['status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.status', 'preparing');
        $this->patchJson($endpoint, ['status' => 'ready_to_ship'])
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.status', 'ready_to_ship');
        $this->patchJson($endpoint, ['status' => 'shipped'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'request.validation_failed')
            ->assertJsonStructure([
                'error' => ['fields' => ['carrier', 'tracking_code']],
            ]);
        $this->patchJson($endpoint, [
            'status' => 'shipped',
            'carrier' => 'پست جمهوری اسلامی',
            'tracking_code' => 'TRACK-001',
            'internal_note' => 'بسته سالم تحویل پست شد.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.sub_orders.0.status', 'shipped')
            ->assertJsonPath('data.sub_orders.0.shipment.carrier', 'پست جمهوری اسلامی')
            ->assertJsonPath('data.sub_orders.0.shipment.tracking_code', 'TRACK-001');
        $this->patchJson($endpoint, ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.sub_orders.0.status', 'delivered')
            ->assertJsonPath('data.sub_orders.0.shipment.status', 'delivered');

        $this->assertDatabaseHas('shipments', [
            'sub_order_id' => $order->subOrder->id,
            'carrier' => 'پست جمهوری اسلامی',
            'tracking_code' => 'TRACK-001',
            'status' => 'delivered',
        ]);
        $this->assertSame(5, SubOrderStatusHistory::query()
            ->where('sub_order_id', $order->subOrder->id)
            ->count());
        $this->assertDatabaseHas('order_internal_notes', [
            'order_id' => $order->id,
            'sub_order_id' => $order->subOrder->id,
            'actor_id' => $seller->id,
        ]);

        $this->authenticateWithRole(
            $foreignSeller,
            Role::RoasteryOwner,
            'roastery',
            (string) Roastery::query()->whereKeyNot($roastery->id)->value('id'),
        );
        $this->patchJson($endpoint, ['status' => 'delivered'])
            ->assertNotFound();
    }

    public function test_rejection_moves_to_refund_pending_and_restocks_exactly_once(): void
    {
        [$seller, , $roastery, $order, $variant] = $this->fixture('reject');
        $this->authenticateWithRole($seller, Role::RoasteryOwner, 'roastery', $roastery->id);
        $endpoint = "/api/v1/seller/roasteries/{$roastery->id}/orders/{$order->id}/fulfillment";

        $this->patchJson($endpoint, [
            'status' => 'rejected',
            'reason' => 'امکان آماده‌سازی این بچ رست وجود ندارد.',
        ])
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.status', 'refund_pending');

        $order->refresh();
        $variant->refresh();
        $this->assertSame(OrderStatus::RefundPending, $order->status);
        $this->assertSame(5, $variant->stock_on_hand);
        $this->assertSame(0, $variant->stock_reserved);
        $this->assertSame(1, InventoryRestock::query()->count());
        $this->assertSame(1, StockLedgerEntry::query()
            ->where('reference_type', 'sub_order')
            ->where('reference_id', $order->subOrder->id)
            ->count());
        $this->assertSame(2, SubOrderStatusHistory::query()
            ->where('sub_order_id', $order->subOrder->id)
            ->count());
        $this->assertDatabaseHas('sub_orders', [
            'id' => $order->subOrder->id,
            'status' => 'refund_pending',
            'rejection_reason' => 'امکان آماده‌سازی این بچ رست وجود ندارد.',
        ]);

        $this->patchJson($endpoint, [
            'status' => 'rejected',
            'reason' => 'تلاش تکراری',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'fulfillment.transition_invalid');

        $variant->refresh();
        $this->assertSame(5, $variant->stock_on_hand);
        $this->assertSame(1, InventoryRestock::query()->count());
    }

    /**
     * @return array{User, User, Roastery, Order, ProductVariant}
     */
    private function fixture(string $suffix): array
    {
        $seller = User::factory()->create();
        $foreignSeller = User::factory()->create();
        $customer = User::factory()->create();
        $roastery = Roastery::query()->create([
            'name' => 'روستری عملیات '.$suffix,
            'slug' => 'fulfillment-roastery-'.$suffix,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $foreignRoastery = Roastery::query()->create([
            'name' => 'روستری خارجی '.$suffix,
            'slug' => 'foreign-fulfillment-roastery-'.$suffix,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        UserRole::query()->create([
            'user_id' => $seller->id,
            'role' => Role::RoasteryOwner,
            'scope_type' => 'roastery',
            'scope_id' => $roastery->id,
        ]);
        UserRole::query()->create([
            'user_id' => $foreignSeller->id,
            'role' => Role::RoasteryOwner,
            'scope_type' => 'roastery',
            'scope_id' => $foreignRoastery->id,
        ]);
        $origin = Origin::query()->create([
            'name' => 'اتیوپی عملیات '.$suffix,
            'slug' => 'fulfillment-origin-'.$suffix,
            'country_code' => 'ETH',
        ]);
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'قهوه عملیات '.$suffix,
            'slug' => 'fulfillment-product-'.$suffix,
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'light',
            'arabica_percentage' => 100,
            'tasting_notes' => ['مرکبات'],
            'brewing_suggestions' => [],
            'status' => 'published',
            'published_at' => now(),
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'FULFILL-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
        ]);
        $quote = CheckoutQuote::query()->create([
            'user_id' => $customer->id,
            'address_id' => null,
            'roastery_id' => $roastery->id,
            'coupon_id' => null,
            'purpose' => 'checkout',
            'payload_hash' => hash('sha256', 'fulfillment-'.$suffix),
            'subtotal' => 4_000_000,
            'shipping_total' => 300_000,
            'discount_total' => 0,
            'grand_total' => 4_300_000,
            'currency' => 'IRR',
            'address_snapshot' => [
                'recipient_name' => 'مشتری تست',
                'recipient_mobile' => '09120000000',
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
            'order_number' => 'R-FULFILL-'.strtoupper($suffix),
            'status' => OrderStatus::Paid,
            'address_snapshot' => $quote->address_snapshot,
            'subtotal' => 4_000_000,
            'shipping_total' => 300_000,
            'discount_total' => 0,
            'grand_total' => 4_300_000,
            'currency' => 'IRR',
            'placed_at' => now()->subMinute(),
            'paid_at' => now(),
        ]);
        $subOrder = SubOrder::query()->create([
            'order_id' => $order->id,
            'roastery_id' => $roastery->id,
            'status' => 'pending_acceptance',
            'subtotal' => 4_000_000,
            'shipping_total' => 300_000,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'roast_batch_id' => null,
            'quantity' => 2,
            'unit_price' => 2_000_000,
            'line_total' => 4_000_000,
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

        return [$seller, $foreignSeller, $roastery, $order->fresh('subOrder'), $variant];
    }
}
