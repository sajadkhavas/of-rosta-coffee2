<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Address;
use App\Models\InventoryReservation;
use App\Models\NotificationOutbox;
use App\Models\Origin;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\ShippingRule;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class PaymentLifecycleTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rosta.payment.enabled' => true,
            'rosta.payment.provider' => 'testing',
            'rosta.payment.amount_multiplier' => 1,
            'rosta.payment.frontend_callback_url' => 'http://localhost:5173/checkout',
            'rosta.allowed_payment_redirect_hosts' => ['localhost', '127.0.0.1'],
            'rosta.notifications.enabled' => false,
            'rosta.notifications.sms_provider' => 'disabled',
        ]);
    }

    public function test_payment_request_is_idempotent_and_callback_consumes_inventory_once(): void
    {
        [$user, $address, $variant] = $this->fixture(stock: 5);
        $this->authenticateWithRole($user, Role::Customer);
        $order = $this->createOrder($address, $variant, 2);
        $payload = [
            'order_id' => $order['id'],
            'idempotency_key' => 'payment:test:idempotency:000001',
        ];

        $first = $this->postJson('/api/v1/payments/request', $payload)
            ->assertCreated()
            ->assertJsonPath('data.payment_id', fn (string $id): bool => $id !== '')
            ->json('data');
        $second = $this->postJson('/api/v1/payments/request', $payload)
            ->assertOk()
            ->assertJsonPath('data.payment_id', $first['payment_id'])
            ->json('data');

        $this->assertSame($first['payment_id'], $second['payment_id']);
        $this->assertDatabaseCount('payment_attempts', 1);

        $this->get('/api/v1/payments/testing/callback/'.$first['payment_id'].'?status=OK')
            ->assertRedirectContains('/checkout?')
            ->assertRedirectContains('payment_id='.$first['payment_id']);

        $this->postJson('/api/v1/payments/'.$first['payment_id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.order_id', $order['id'])
            ->assertJsonPath('data.order_status', 'paid')
            ->assertJsonPath('data.amount', 4_300_000)
            ->assertJsonPath('data.currency', 'IRR');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'order_id' => $order['id'],
            'status' => 'consumed',
        ]);
        $this->assertNotNull(
            InventoryReservation::query()
                ->where('order_id', $order['id'])
                ->value('consumed_at'),
        );
        $this->assertDatabaseHas('orders', [
            'id' => $order['id'],
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('sub_orders', [
            'order_id' => $order['id'],
            'status' => 'accepted',
            'acceptance_status' => 'accepted',
            'sla_status' => 'on_track',
        ]);
        $this->assertNotNull(
            SubOrder::query()
                ->where('order_id', $order['id'])
                ->value('fulfillment_committed_at'),
        );
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order['id'],
            'event_type' => 'fulfillment.committed',
            'next_state' => 'accepted',
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $first['payment_id'],
            'status' => 'verified',
        ]);
        $this->assertSame(1, NotificationOutbox::query()
            ->where('order_id', $order['id'])
            ->where('template_key', 'order.paid')
            ->count());

        $this->get('/api/v1/payments/testing/callback/'.$first['payment_id'].'?status=OK')
            ->assertRedirect();
        $this->postJson('/api/v1/payments/'.$first['payment_id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $variant->refresh();
        $this->assertSame(3, $variant->stock_on_hand);
        $this->assertSame(0, $variant->stock_reserved);
        $this->assertSame(1, NotificationOutbox::query()
            ->where('order_id', $order['id'])
            ->where('template_key', 'order.paid')
            ->count());
    }

    public function test_verified_gateway_truth_moves_to_review_when_reservation_is_not_consumable(): void
    {
        [$user, $address, $variant] = $this->fixture(stock: 5, suffix: 'review');
        $this->authenticateWithRole($user, Role::Customer);
        $order = $this->createOrder($address, $variant, 1, 'review');
        $payment = $this->postJson('/api/v1/payments/request', [
            'order_id' => $order['id'],
            'idempotency_key' => 'payment:test:review-required:0001',
        ])->assertCreated()->json('data');

        InventoryReservation::query()
            ->where('order_id', $order['id'])
            ->update(['expires_at' => now()->subMinute()]);

        $this->get('/api/v1/payments/testing/callback/'.$payment['payment_id'].'?status=OK')
            ->assertRedirect();

        $this->postJson('/api/v1/payments/'.$payment['payment_id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.order_status', 'awaiting_payment');

        $attempt = PaymentAttempt::query()->findOrFail($payment['payment_id']);
        $this->assertSame('requires_review', $attempt->status->value);
        $this->assertSame('reservation_unavailable', $attempt->failure_code);
        $variant->refresh();
        $this->assertSame(5, $variant->stock_on_hand);
        $this->assertSame(1, $variant->stock_reserved);
        $this->assertDatabaseMissing('notification_outbox', [
            'order_id' => $order['id'],
            'template_key' => 'order.paid',
        ]);
    }

    public function test_payment_result_is_hidden_from_another_customer(): void
    {
        [$user, $address, $variant] = $this->fixture(stock: 5, suffix: 'owner');
        $this->authenticateWithRole($user, Role::Customer);
        $order = $this->createOrder($address, $variant, 1, 'owner');
        $payment = $this->postJson('/api/v1/payments/request', [
            'order_id' => $order['id'],
            'idempotency_key' => 'payment:test:ownership:000001',
        ])->assertCreated()->json('data');

        $foreignUser = User::factory()->create();
        $this->authenticateWithRole($foreignUser, Role::Customer);

        $this->postJson('/api/v1/payments/'.$payment['payment_id'].'/verify')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'request.not_found');
    }

    /** @return array{User, Address, ProductVariant} */
    private function fixture(int $stock, string $suffix = 'main'): array
    {
        $user = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'title' => 'خانه',
            'recipient_name' => 'سجاد',
            'recipient_mobile' => '09123456789',
            'province' => 'البرز',
            'city' => 'کرج',
            'address_line' => 'نشانی تست پرداخت',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);
        $roastery = Roastery::query()->create([
            'name' => 'روستری پرداخت '.$suffix,
            'slug' => 'payment-roastery-'.$suffix,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        ShippingRule::query()->create([
            'roastery_id' => $roastery->id,
            'province' => null,
            'city' => null,
            'base_cost' => 300_000,
            'free_over' => 10_000_000,
            'priority' => 0,
            'is_active' => true,
        ]);
        $origin = Origin::query()->create([
            'name' => 'اتیوپی پرداخت '.$suffix,
            'slug' => 'payment-origin-'.$suffix,
            'country_code' => 'ETH',
        ]);
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'محصول پرداخت '.$suffix,
            'slug' => 'payment-product-'.$suffix,
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
            'sku' => 'PAYMENT-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => $stock,
            'stock_reserved' => 0,
        ]);
        RoastBatch::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'PAYMENT-BATCH-'.strtoupper($suffix),
            'roasted_at' => now()->subDay(),
            'available_from' => now()->subHours(12),
            'is_active' => true,
        ]);

        return [$user, $address, $variant];
    }

    /** @return array<string, mixed> */
    private function createOrder(
        Address $address,
        ProductVariant $variant,
        int $quantity,
        string $suffix = 'main',
    ): array {
        $quote = $this->postJson('/api/v1/checkout/quote', [
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => $quantity,
            ]],
            'address_id' => $address->id,
        ])->assertOk()->json('data');

        return $this->postJson('/api/v1/orders', [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'payment:test:order:'.$suffix.':000001',
        ])->assertCreated()->json('data');
    }
}
