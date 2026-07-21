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

final class OrderOperationalScopeTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_customer_seller_and_admin_order_views_preserve_ownership(): void
    {
        [$customer, $address, $roastery, $variant] = $this->fixture();
        $this->authenticateWithRole($customer, Role::Customer);

        $quote = $this->postJson('/api/v1/checkout/quote', [
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'address_id' => $address->id,
        ])->assertOk()->json('data');

        $order = $this->postJson('/api/v1/orders', [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'checkout:operational:scope:0001',
        ])->assertCreated()->json('data');

        $seller = User::factory()->create();
        $this->authenticateWithRole(
            $seller,
            Role::RoasteryOwner,
            'roastery',
            $roastery->id,
        );

        $this->getJson('/api/v1/seller/roasteries/'.$roastery->id.'/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order['id']);

        $this->getJson('/api/v1/seller/roasteries/'.$roastery->id.'/orders/'.$order['id'])
            ->assertOk()
            ->assertJsonPath('data.id', $order['id']);

        $foreignRoastery = Roastery::query()->create([
            'name' => 'روستری دیگر',
            'slug' => 'other-roastery',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        $foreignSeller = User::factory()->create();
        $this->authenticateWithRole(
            $foreignSeller,
            Role::RoasteryOwner,
            'roastery',
            $foreignRoastery->id,
        );

        $this->getJson('/api/v1/seller/roasteries/'.$roastery->id.'/orders/'.$order['id'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'request.not_found');

        $this->getJson('/api/v1/seller/roasteries/'.$foreignRoastery->id.'/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);

        $this->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order['id']);

        $this->getJson('/api/v1/admin/orders/'.$order['id'])
            ->assertOk()
            ->assertJsonPath('data.id', $order['id']);
    }

    /** @return array{User, Address, Roastery, ProductVariant} */
    private function fixture(): array
    {
        $customer = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'title' => 'خانه',
            'recipient_name' => 'سجاد',
            'recipient_mobile' => '09123456789',
            'province' => 'تهران',
            'city' => 'تهران',
            'address_line' => 'نشانی تست',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);

        $roastery = Roastery::query()->create([
            'name' => 'روستری سفارش',
            'slug' => 'order-roastery',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        ShippingRule::query()->create([
            'roastery_id' => $roastery->id,
            'base_cost' => 100_000,
            'priority' => 0,
            'is_active' => true,
        ]);

        $origin = Origin::query()->create([
            'name' => 'برزیل سفارش',
            'slug' => 'order-origin',
            'country_code' => 'BRA',
        ]);

        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'محصول سفارش',
            'slug' => 'order-product',
            'description' => '',
            'processing_method' => 'natural',
            'roast_level' => 'medium',
            'arabica_percentage' => 100,
            'tasting_notes' => [],
            'brewing_suggestions' => [],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'ORDER-250',
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
        ]);

        return [$customer, $address, $roastery, $variant];
    }
}
