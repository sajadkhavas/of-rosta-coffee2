<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\ShippingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class CheckoutFinancialBoundariesTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_coupon_capacity_is_released_when_unpaid_order_is_cancelled(): void
    {
        [$user, $address, $roastery, $variant] = $this->fixture(2_000_000);
        $this->authenticateWithRole($user, Role::Customer);

        $coupon = Coupon::query()->create([
            'roastery_id' => $roastery->id,
            'code' => 'ONEUSE',
            'type' => 'fixed',
            'value' => 500_000,
            'minimum_subtotal' => 0,
            'max_redemptions' => 1,
            'redemption_count' => 0,
            'is_active' => true,
        ]);

        $quote = $this->postJson('/api/v1/checkout/quote', [
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'address_id' => $address->id,
            'coupon_code' => 'oneuse',
        ])
            ->assertOk()
            ->assertJsonPath('data.discount_total', 500_000)
            ->json('data');

        $order = $this->postJson('/api/v1/orders', [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'checkout:coupon:reservation:0001',
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'redemption_count' => 1,
        ]);

        $this->postJson('/api/v1/orders/'.$order['id'].'/cancel', [
            'reason' => 'لغو تست',
        ])->assertOk();

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'redemption_count' => 0,
        ]);

        $this->postJson('/api/v1/orders/'.$order['id'].'/cancel', [
            'reason' => 'تکرار لغو',
        ])->assertOk();

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'redemption_count' => 0,
        ]);
    }

    public function test_quote_rejects_money_outside_contract_range(): void
    {
        [, , , $variant] = $this->fixture(9_007_199_254_740_991);

        $this->postJson('/api/v1/cart/validate', [
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => 2,
            ]],
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'checkout.amount_out_of_range');
    }

    /** @return array{User, Address, Roastery, ProductVariant} */
    private function fixture(int $price): array
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
            'name' => 'روستری مالی',
            'slug' => 'financial-roastery-'.substr((string) $user->id, -6),
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        ShippingRule::query()->create([
            'roastery_id' => $roastery->id,
            'base_cost' => 0,
            'priority' => 0,
            'is_active' => true,
        ]);

        $origin = Origin::query()->create([
            'name' => 'کنیا مالی',
            'slug' => 'financial-origin-'.substr((string) $user->id, -6),
            'country_code' => 'KEN',
        ]);

        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'محصول مالی',
            'slug' => 'financial-product-'.substr((string) $user->id, -6),
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'medium',
            'arabica_percentage' => 100,
            'tasting_notes' => [],
            'brewing_suggestions' => [],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'FIN-'.substr((string) $user->id, -8),
            'weight_grams' => 250,
            'price' => $price,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
        ]);

        return [$user, $address, $roastery, $variant];
    }
}
