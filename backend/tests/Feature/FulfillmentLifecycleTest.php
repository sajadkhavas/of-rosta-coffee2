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

    public function test_seller_only_prepares_and_hands_off_a_contractually_accepted_order(): void
    {
        [$seller, $foreignSeller, $roastery, $order] = $this->fixture('flow');
        $this->authenticateWithRole($seller, Role::RoasteryOwner, 'roastery', $roastery->id);
        $endpoint = "/api/v1/seller/roasteries/{$roastery->id}/orders/{$order->id}/fulfillment";

        $this->patchJson($endpoint, ['status' => 'accepted'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'request.validation_failed');
        $this->patchJson($endpoint, ['status' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'request.validation_failed');

        $this->patchJson($endpoint, [
            'status' => 'shipped',
            'carrier' => 'پست',
            'tracking_code' => 'TRACK-001',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'fulfillment.transition_invalid');

        $this->patchJson($endpoint, ['status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
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
            ->assertConflict()
            ->assertJsonPath('error.code', 'fulfillment.transition_invalid');

        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);
        $this->patchJson("/api/v1/admin/orders/{$order->id}/fulfillment", ['status' => 'delivered'])
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
        $this->assertSame(4, SubOrderStatusHistory::query()
            ->where('sub_order_id', $order->subOrder->id)
            ->count());
        $this->assertDatabaseHas('order_internal_notes', [
            'order_id' => $order->id,
            'sub_order_id' => $order->subOrder->id,
            'actor_id' => $seller->id,
        ]);

        $foreignRoasteryId = (string) Roastery::query()
            ->where('id', '!=', $roastery->id)
            ->value('id');
        $this->authenticateWithRole(
            $foreignSeller,
            Role::RoasteryOwner,
            'roastery',
            $foreignRoasteryId,
        );
        $this->patchJson($endpoint, ['status' => 'preparing'])
            ->assertNotFound();
    }

    public function test_incident_pauses_transitions_without_rejecting_or_mutating_inventory(): void
    {
        [$seller, , $roastery, $order, $variant] = $this->fixture('incident');
        $this->authenticateWithRole($seller, Role::RoasteryOwner, 'roastery', $roastery->id);
        $incidentEndpoint = "/api/v1/seller/roasteries/{$roastery->id}/orders/{$order->id}/incidents";

        $response = $this->postJson($incidentEndpoint, [
            'code' => 'equipment_failure',
            'description' => 'دستگاه بسته‌بندی دچار خرابی اضطراری شده است.',
            'severity' => 'high',
        ])
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.status', 'accepted')
            ->assertJsonPath('data.sub_orders.0.fulfillment.sla_status', 'exception_open')
            ->assertJsonPath('data.sub_orders.0.incidents.0.status', 'open');

        $incidentId = (string) $response->json('data.sub_orders.0.incidents.0.id');
        $variant->refresh();
        $this->assertSame(3, $variant->stock_on_hand);
        $this->assertDatabaseCount('inventory_restocks', 0);
        $this->assertDatabaseHas('sub_orders', [
            'id' => $order->subOrder->id,
            'status' => 'accepted',
            'sla_status' => 'exception_open',
        ]);

        $this->patchJson(
            "/api/v1/seller/roasteries/{$roastery->id}/orders/{$order->id}/fulfillment",
            ['status' => 'preparing'],
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'fulfillment.incident_resolution_required');

        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);
        $beforeHandoff = $order->subOrder->handoff_due_at;
        $this->postJson("/api/v1/admin/fulfillment-incidents/{$incidentId}/resolve", [
            'resolution' => 'resume_fulfillment',
            'note' => 'تجهیزات جایگزین تأیید شد و آماده‌سازی ادامه دارد.',
            'extend_sla_hours' => 12,
        ])
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.status', 'accepted')
            ->assertJsonPath('data.sub_orders.0.fulfillment.sla_status', 'exception_resolved')
            ->assertJsonPath('data.sub_orders.0.incidents.0.status', 'resolved');

        $order->subOrder->refresh();
        $this->assertTrue($order->subOrder->handoff_due_at->greaterThan($beforeHandoff));
        $this->assertDatabaseCount('inventory_restocks', 0);
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
            'status' => 'accepted',
            'acceptance_status' => 'accepted',
            'subtotal' => 4_000_000,
            'shipping_total' => 300_000,
            'packaging_total' => 0,
            'grinding_total' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 4_300_000,
            'commission_total' => 0,
            'payable_total' => 4_300_000,
            'currency' => 'IRR',
            'accepted_at' => now(),
            'fulfillment_committed_at' => now(),
            'preparation_due_at' => now()->addDay(),
            'handoff_due_at' => now()->addDays(2),
            'sla_status' => 'on_track',
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
