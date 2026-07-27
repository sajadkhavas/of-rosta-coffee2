<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Address;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\ShippingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class R5CMultiRoasteryCheckoutTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_one_quote_creates_one_parent_and_two_sub_orders_atomically(): void
    {
        $customer = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'title' => 'خانه',
            'recipient_name' => 'مشتری بازار',
            'recipient_mobile' => '09120000000',
            'province' => 'تهران',
            'city' => 'تهران',
            'address_line' => 'نشانی بازار چندروستری',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);

        [$firstRoastery, $firstVariant] = $this->catalogFixture('alpha', 2_000_000, 250_000);
        [$secondRoastery, $secondVariant] = $this->catalogFixture('beta', 3_000_000, 350_000);

        $this->authenticateWithRole($customer, Role::Customer);

        $quote = $this->postJson('/api/v1/checkout/quote', [
            'items' => [
                ['variant_id' => $firstVariant->id, 'quantity' => 2],
                ['variant_id' => $secondVariant->id, 'quantity' => 1],
            ],
            'address_id' => $address->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.roastery_id', null)
            ->assertJsonCount(2, 'data.groups')
            ->assertJsonPath('data.subtotal', 7_000_000)
            ->assertJsonPath('data.shipping_total', 600_000)
            ->assertJsonPath('data.grand_total', 7_600_000)
            ->json('data');

        $payload = [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'r5c:marketplace-order:0001',
            'notes' => 'ارسال هر روستری جداگانه قابل قبول است.',
        ];

        $order = $this->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonCount(2, 'data.sub_orders')
            ->assertJsonCount(3, 'data.events')
            ->json('data');

        $this->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $order['id']);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('sub_orders', 2);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseCount('inventory_reservations', 2);
        $this->assertDatabaseCount('shipment_legs', 2);
        $this->assertDatabaseCount('settlement_allocations', 4);
        $this->assertDatabaseCount('order_events', 3);

        $this->assertDatabaseHas('orders', [
            'id' => $order['id'],
            'roastery_id' => null,
            'subtotal' => 7_000_000,
            'shipping_total' => 600_000,
            'grand_total' => 7_600_000,
        ]);
        $this->assertDatabaseHas('sub_orders', [
            'order_id' => $order['id'],
            'roastery_id' => $firstRoastery->id,
            'status' => 'awaiting_payment',
            'acceptance_status' => 'awaiting_payment',
            'subtotal' => 4_000_000,
            'shipping_total' => 250_000,
            'grand_total' => 4_250_000,
        ]);
        $this->assertDatabaseHas('sub_orders', [
            'order_id' => $order['id'],
            'roastery_id' => $secondRoastery->id,
            'status' => 'awaiting_payment',
            'acceptance_status' => 'awaiting_payment',
            'subtotal' => 3_000_000,
            'shipping_total' => 350_000,
            'grand_total' => 3_350_000,
        ]);
        $this->assertDatabaseHas('settlement_allocations', [
            'order_id' => $order['id'],
            'allocation_type' => 'product',
            'owner_type' => 'roastery',
            'owner_id' => $firstRoastery->id,
            'gross_amount' => 4_000_000,
            'net_amount' => 4_000_000,
        ]);
        $this->assertDatabaseHas('settlement_allocations', [
            'order_id' => $order['id'],
            'allocation_type' => 'shipping',
            'owner_type' => 'roastery',
            'owner_id' => $secondRoastery->id,
            'gross_amount' => 350_000,
            'net_amount' => 350_000,
        ]);
    }

    /** @return array{Roastery, ProductVariant} */
    private function catalogFixture(string $suffix, int $price, int $shipping): array
    {
        $roastery = Roastery::query()->create([
            'name' => 'روستری '.$suffix,
            'slug' => 'r5c-roastery-'.$suffix,
            'city' => 'تهران',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        ShippingRule::query()->create([
            'roastery_id' => $roastery->id,
            'province' => null,
            'city' => null,
            'base_cost' => $shipping,
            'free_over' => null,
            'priority' => 0,
            'is_active' => true,
        ]);

        $origin = Origin::query()->create([
            'name' => 'خاستگاه '.$suffix,
            'slug' => 'r5c-origin-'.$suffix,
            'country_code' => 'ETH',
        ]);

        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'قهوه '.$suffix,
            'slug' => 'r5c-product-'.$suffix,
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
            'sku' => 'R5C-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => $price,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
        ]);

        return [$roastery, $variant];
    }
}
