<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Address;
use App\Models\InventoryReservation;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\ShippingRule;
use App\Models\User;
use App\Services\Checkout\ReservationExpiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class TransactionalCheckoutTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_checkout_quote_and_order_reserve_stock_idempotently(): void
    {
        [$user, $address, $roastery, $variant] = $this->fixture(stock: 5);
        $this->authenticateWithRole($user, Role::Customer);

        $quote = $this->postJson('/api/v1/checkout/quote', [
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => 2,
            ]],
            'address_id' => $address->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.roastery_id', $roastery->id)
            ->assertJsonPath('data.subtotal', 4_000_000)
            ->assertJsonPath('data.shipping_total', 300_000)
            ->assertJsonPath('data.grand_total', 4_300_000)
            ->json('data');

        $payload = [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'checkout:test:idempotency:0001',
            'notes' => 'تحویل در ساعات اداری',
        ];

        $first = $this->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.sub_orders.0.items.0.variant.weight_grams', 250)
            ->json('data');

        $second = $this->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $first['id'])
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock_on_hand' => 5,
            'stock_reserved' => 2,
        ]);
    }

    public function test_idempotency_key_cannot_be_reused_with_another_payload(): void
    {
        [$user, $address, , $variant] = $this->fixture(stock: 5);
        $this->authenticateWithRole($user, Role::Customer);

        $quote = $this->checkoutQuote($address, $variant, 1);

        $this->postJson('/api/v1/orders', [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'checkout:test:idempotency:0002',
            'notes' => 'اول',
        ])->assertCreated();

        $this->postJson('/api/v1/orders', [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'checkout:test:idempotency:0002',
            'notes' => 'متفاوت',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'order.idempotency_conflict');
    }

    public function test_order_creation_rejects_stale_price_and_overselling(): void
    {
        [$user, $address, , $variant] = $this->fixture(stock: 5);
        $this->authenticateWithRole($user, Role::Customer);

        $staleQuote = $this->checkoutQuote($address, $variant, 1);
        $variant->forceFill(['price' => 2_500_000])->save();

        $this->postJson('/api/v1/orders', [
            'quote_id' => $staleQuote['id'],
            'idempotency_key' => 'checkout:test:stale-price:0001',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'checkout.catalog_changed');

        $variant->forceFill(['price' => 2_000_000])->save();
        $firstQuote = $this->checkoutQuote($address, $variant, 4);
        $secondQuote = $this->checkoutQuote($address, $variant, 2);

        $this->postJson('/api/v1/orders', [
            'quote_id' => $firstQuote['id'],
            'idempotency_key' => 'checkout:test:reserve:first:0001',
        ])->assertCreated();

        $this->postJson('/api/v1/orders', [
            'quote_id' => $secondQuote['id'],
            'idempotency_key' => 'checkout:test:reserve:second:0001',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'checkout.insufficient_stock');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock_reserved' => 4,
        ]);
    }

    public function test_customer_cancellation_and_expiry_release_reservations(): void
    {
        [$user, $address, , $variant] = $this->fixture(stock: 5);
        $this->authenticateWithRole($user, Role::Customer);

        $quote = $this->checkoutQuote($address, $variant, 2);
        $order = $this->postJson('/api/v1/orders', [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'checkout:test:cancel:0001',
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/orders/'.$order['id'].'/cancel', [
            'reason' => 'تغییر تصمیم',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock_reserved' => 0,
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'order_id' => $order['id'],
            'status' => 'released',
        ]);

        $secondQuote = $this->checkoutQuote($address, $variant, 1);
        $secondOrder = $this->postJson('/api/v1/orders', [
            'quote_id' => $secondQuote['id'],
            'idempotency_key' => 'checkout:test:expire:0001',
        ])->assertCreated()->json('data');

        InventoryReservation::query()
            ->where('order_id', $secondOrder['id'])
            ->update(['expires_at' => now()->subMinute()]);

        $expired = app(ReservationExpiryService::class)->expireDue();

        $this->assertSame(1, $expired);
        $this->assertDatabaseHas('orders', [
            'id' => $secondOrder['id'],
            'status' => 'cancelled',
            'cancellation_reason' => 'reservation_expired',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock_reserved' => 0,
        ]);
    }

    public function test_cart_rejects_multiple_roasteries_and_order_ownership_is_hidden(): void
    {
        [$user, $address, , $variant] = $this->fixture(stock: 5);
        [, , , $foreignVariant] = $this->fixture(stock: 5, suffix: 'foreign');

        $this->postJson('/api/v1/cart/validate', [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
                ['variant_id' => $foreignVariant->id, 'quantity' => 1],
            ],
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'cart.multiple_roasteries');

        $this->authenticateWithRole($user, Role::Customer);
        $quote = $this->checkoutQuote($address, $variant, 1);
        $order = $this->postJson('/api/v1/orders', [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'checkout:test:ownership:0001',
        ])->assertCreated()->json('data');

        $other = User::factory()->create();
        $this->authenticateWithRole($other, Role::Customer);

        $this->getJson('/api/v1/orders/'.$order['id'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'request.not_found');
    }

    /** @return array{User, Address, Roastery, ProductVariant} */
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
            'address_line' => 'نشانی تست',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);

        $roastery = Roastery::query()->create([
            'name' => 'روستری '.$suffix,
            'slug' => 'roastery-'.$suffix,
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
            'name' => 'اتیوپی '.$suffix,
            'slug' => 'ethiopia-'.$suffix,
            'country_code' => 'ETH',
        ]);

        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'محصول '.$suffix,
            'slug' => 'product-'.$suffix,
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
            'sku' => 'SKU-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => $stock,
            'stock_reserved' => 0,
        ]);

        RoastBatch::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'BATCH-'.strtoupper($suffix),
            'roasted_at' => now()->subDay(),
            'available_from' => now()->subHours(12),
            'is_active' => true,
        ]);

        return [$user, $address, $roastery, $variant];
    }

    /** @return array<string, mixed> */
    private function checkoutQuote(Address $address, ProductVariant $variant, int $quantity): array
    {
        return $this->postJson('/api/v1/checkout/quote', [
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => $quantity,
            ]],
            'address_id' => $address->id,
        ])->assertOk()->json('data');
    }
}
