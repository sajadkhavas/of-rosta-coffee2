<?php

namespace Tests\Feature;

use App\Enums\CafeStatus;
use App\Enums\Role;
use App\Models\Address;
use App\Models\Cafe;
use App\Models\CafeMembership;
use App\Models\CheckoutQuoteItem;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\ShippingRule;
use App\Models\User;
use App\Models\WholesalePriceTier;
use App\Services\B2B\WholesalePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class PS12CafeWholesaleTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_wholesale_tiers_require_verified_cafe_and_resolve_at_exact_weight_boundaries(): void
    {
        [$user, , , $variant] = $this->fixture();
        foreach ([[5_000, 900_000], [10_000, 850_000], [20_000, 800_000], [50_000, 750_000]] as [$weight, $price]) {
            WholesalePriceTier::query()->create(['product_variant_id' => $variant->id, 'min_weight_grams' => $weight, 'unit_price' => $price, 'is_active' => true]);
        }

        $pricing = app(WholesalePricingService::class);
        self::assertSame(1_000_000, $pricing->resolve($user, $variant, 50)['unit_price']);

        $cafe = Cafe::query()->create(['owner_user_id' => $user->id, 'name' => 'کافه تست', 'slug' => 'test-cafe', 'status' => CafeStatus::Pending, 'city' => 'کرج', 'address' => 'مرکز شهر']);
        CafeMembership::query()->create(['cafe_id' => $cafe->id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => true, 'created_by_id' => $user->id]);
        self::assertSame(1_000_000, $pricing->resolve($user, $variant, 5)['unit_price']);

        $cafe->forceFill(['status' => CafeStatus::Verified, 'verified_at' => now()])->save();
        self::assertSame(900_000, $pricing->resolve($user, $variant, 5)['unit_price']);
        self::assertSame(850_000, $pricing->resolve($user, $variant, 10)['unit_price']);
        self::assertSame(800_000, $pricing->resolve($user, $variant, 20)['unit_price']);
        self::assertSame(750_000, $pricing->resolve($user, $variant, 50)['unit_price']);
    }

    public function test_verified_cafe_gets_server_authoritative_wholesale_quote_snapshot(): void
    {
        [$user, $address, , $variant] = $this->fixture();
        $this->authenticateWithRole($user, Role::Customer);
        $cafe = Cafe::query()->create(['owner_user_id' => $user->id, 'name' => 'کافه عمده', 'slug' => 'wholesale-cafe', 'status' => CafeStatus::Verified, 'city' => 'کرج', 'address' => 'مرکز', 'verified_at' => now()]);
        CafeMembership::query()->create(['cafe_id' => $cafe->id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => true, 'created_by_id' => $user->id]);
        WholesalePriceTier::query()->create(['product_variant_id' => $variant->id, 'min_weight_grams' => 5_000, 'unit_price' => 900_000, 'is_active' => true]);

        $quote = $this->postJson('/api/v1/checkout/quote', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 5]],
            'address_id' => $address->id,
        ])->assertOk()->json('data');

        self::assertSame(4_500_000, $quote['groups'][0]['items'][0]['line_total']);
        self::assertSame(900_000, $quote['groups'][0]['items'][0]['variant']['pricing']['applied_unit_price']);
        self::assertSame('ps12-wholesale-tier-v1', $quote['groups'][0]['items'][0]['variant']['pricing']['version']);
        self::assertSame('wholesale', $quote['groups'][0]['items'][0]['variant']['pricing']['mode']);
        $this->assertDatabaseHas('checkout_quote_items', ['quote_id' => $quote['id'], 'variant_id' => $variant->id, 'unit_price' => 900_000, 'line_total' => 4_500_000]);
        $stored = CheckoutQuoteItem::query()->where('quote_id', $quote['id'])->firstOrFail();
        self::assertSame('ps12-wholesale-tier-v1', $stored->variant_snapshot['pricing']['version']);
        self::assertSame('wholesale', $stored->variant_snapshot['pricing']['mode']);
    }

    public function test_public_directory_returns_only_verified_nearby_cafes_sorted_by_distance(): void
    {
        $owner = User::factory()->create();
        Cafe::query()->create(['owner_user_id' => $owner->id, 'name' => 'نزدیک', 'slug' => 'near-cafe', 'status' => CafeStatus::Verified, 'city' => 'کرج', 'address' => 'نزدیک', 'latitude' => 35.8327, 'longitude' => 50.9915, 'verified_at' => now()]);
        Cafe::query()->create(['owner_user_id' => $owner->id, 'name' => 'دورتر', 'slug' => 'farther-cafe', 'status' => CafeStatus::Verified, 'city' => 'کرج', 'address' => 'دورتر', 'latitude' => 35.8500, 'longitude' => 51.0100, 'verified_at' => now()]);
        Cafe::query()->create(['owner_user_id' => $owner->id, 'name' => 'در انتظار', 'slug' => 'pending-cafe', 'status' => CafeStatus::Pending, 'city' => 'کرج', 'address' => 'پنهان', 'latitude' => 35.8328, 'longitude' => 50.9916]);

        $response = $this->getJson('/api/v1/cafes?lat=35.8325&lng=50.9912&radius_km=10')->assertOk();
        self::assertSame('near-cafe', $response->json('data.items.0.slug'));
        self::assertCount(2, $response->json('data.items'));
    }

    public function test_cafe_application_is_pending_until_admin_verifies_it(): void
    {
        $owner = User::factory()->create();
        $this->authenticateWithRole($owner, Role::Customer);
        $cafe = $this->postJson('/api/v1/cafes/apply', ['name' => 'کافه درخواست', 'slug' => 'apply-cafe', 'city' => 'کرج', 'address' => 'آدرس کافه', 'latitude' => 35.83, 'longitude' => 50.99])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');

        $admin = User::factory()->create();
        $this->authenticateWithRole($admin, Role::Administrator);
        $this->patchJson('/api/v1/admin/cafes/'.$cafe['id'].'/status', ['status' => 'verified'])
            ->assertOk()->assertJsonPath('data.status', 'verified');
        $this->assertDatabaseHas('user_roles', ['user_id' => $owner->id, 'role' => Role::CafeOwner->value, 'scope_type' => 'cafe', 'scope_id' => $cafe['id']]);
    }

    /** @return array{User,Address,Roastery,ProductVariant} */
    private function fixture(): array
    {
        $user = User::factory()->create();
        $address = Address::query()->create(['user_id' => $user->id, 'title' => 'کافه', 'recipient_name' => 'مدیر', 'recipient_mobile' => '09123456789', 'province' => 'البرز', 'city' => 'کرج', 'address_line' => 'نشانی', 'postal_code' => '1234567890', 'is_default' => true]);
        $roastery = Roastery::query()->create(['name' => 'روستری B2B', 'slug' => 'b2b-roastery-'.substr((string) $user->id, -6), 'description' => '', 'status' => 'verified', 'verified_at' => now()]);
        ShippingRule::query()->create(['roastery_id' => $roastery->id, 'base_cost' => 0, 'priority' => 0, 'is_active' => true]);
        $origin = Origin::query()->create(['name' => 'برزیل B2B', 'slug' => 'b2b-origin-'.substr((string) $user->id, -6), 'country_code' => 'BRA']);
        $product = Product::query()->create(['roastery_id' => $roastery->id, 'origin_id' => $origin->id, 'name' => 'قهوه B2B', 'slug' => 'b2b-product-'.substr((string) $user->id, -6), 'description' => '', 'processing_method' => 'washed', 'roast_level' => 'medium', 'arabica_percentage' => 100, 'tasting_notes' => [], 'brewing_suggestions' => [], 'status' => 'published', 'published_at' => now()]);
        $variant = ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'B2B-'.substr((string) $user->id, -8), 'weight_grams' => 1000, 'price' => 1_000_000, 'currency' => 'IRR', 'is_active' => true, 'stock_on_hand' => 1000, 'stock_reserved' => 0]);

        return [$user, $address, $roastery, $variant];
    }
}
