<?php

namespace Tests\Feature;

use App\Exceptions\ApiDomainException;
use App\Models\Address;
use App\Models\GrindingProfile;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\RoasteryGrindingCapability;
use App\Models\ShippingRule;
use App\Models\User;
use App\Services\Checkout\OrderService;
use App\Services\Checkout\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class R5FRoasteryGrindingSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_roastery_grinding_is_snapshotted_priced_and_allocated_without_changing_inventory_identity(): void
    {
        $customer = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'title' => 'خانه',
            'recipient_name' => 'مشتری آسیاب',
            'recipient_mobile' => '09120000000',
            'province' => 'تهران',
            'city' => 'تهران',
            'address_line' => 'نشانی آزمون R5F',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);

        [$roastery, $variant] = $this->catalogFixture();
        $profile = $this->profile('v60', 2, 'V60');
        $capability = RoasteryGrindingCapability::query()->create([
            'roastery_id' => $roastery->id,
            'availability' => 'available',
            'fee_mode' => 'fixed',
            'fee_amount' => 75_000,
            'preparation_minutes' => 20,
            'capacity_per_day' => 10,
            'supported_weights' => [250],
            'is_active' => true,
        ]);
        $capability->profiles()->sync([$profile->id]);

        $quote = app(QuoteService::class)->createCheckoutQuote(
            $customer,
            $address,
            [[
                'variant_id' => $variant->id,
                'quantity' => 2,
                'grinding_profile_id' => $profile->id,
            ]],
            null,
        );
        $quote->load('groups.items.services.grindingProfile');

        $group = $quote->groups->firstOrFail();
        $grinding = $group->items->firstOrFail()->services
            ->firstWhere('service_type', 'grinding');

        $this->assertSame(2_000_000, $quote->subtotal);
        $this->assertSame(150_000, $group->grinding_total);
        $this->assertSame(100_000, $group->shipping_total);
        $this->assertSame(2_250_000, $quote->grand_total);
        $this->assertNotNull($grinding);
        $this->assertSame($profile->id, $grinding->grinding_profile_id);
        $this->assertSame(150_000, $grinding->service_fee);
        $this->assertSame('آسیاب روستری: V60', $grinding->service_snapshot['label']);
        $this->assertSame(250, $grinding->service_snapshot['weight_grams']);
        $this->assertSame(2, $grinding->service_snapshot['quantity']);

        $request = Request::create('/api/v1/orders', 'POST');
        $request->headers->set('X-Request-ID', 'r5f-grinding-test');
        $order = app(OrderService::class)->create(
            $customer,
            $quote->id,
            'r5f:grinding-order:0001',
            null,
            $request,
        );

        $subOrder = $order->subOrders->firstOrFail();
        $orderGrinding = $subOrder->items->firstOrFail()->services
            ->firstWhere('service_type', 'grinding');

        $this->assertSame(150_000, $subOrder->grinding_total);
        $this->assertSame(2_250_000, $order->grand_total);
        $this->assertSame($profile->id, $orderGrinding?->grinding_profile_id);
        $this->assertSame('requested', $orderGrinding?->status->value);
        $this->assertDatabaseHas('settlement_allocations', [
            'order_id' => $order->id,
            'order_item_service_id' => $orderGrinding?->id,
            'allocation_type' => 'grinding',
            'owner_type' => 'roastery',
            'owner_id' => $roastery->id,
            'gross_amount' => 150_000,
            'net_amount' => 150_000,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'order_item_service_id' => $orderGrinding?->id,
            'event_type' => 'grinding.requested',
        ]);

        $variant->refresh();
        $this->assertSame(250, $variant->weight_grams);
        $this->assertSame(10, $variant->stock_on_hand);
        $this->assertSame(2, $variant->stock_reserved);
    }

    public function test_roastery_grinding_rejects_profiles_outside_the_capability(): void
    {
        $customer = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'title' => 'خانه',
            'recipient_name' => 'مشتری آسیاب',
            'recipient_mobile' => '09120000000',
            'province' => 'تهران',
            'city' => 'تهران',
            'address_line' => 'نشانی آزمون R5F',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);
        [$roastery, $variant] = $this->catalogFixture();
        $allowed = $this->profile('v60', 2, 'V60');
        $unsupported = $this->profile('chemex', 1, 'Chemex');
        $capability = RoasteryGrindingCapability::query()->create([
            'roastery_id' => $roastery->id,
            'availability' => 'available',
            'fee_mode' => 'free',
            'fee_amount' => 0,
            'preparation_minutes' => 10,
            'capacity_per_day' => null,
            'supported_weights' => [250],
            'is_active' => true,
        ]);
        $capability->profiles()->sync([$allowed->id]);

        try {
            app(QuoteService::class)->createCheckoutQuote(
                $customer,
                $address,
                [[
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'grinding_profile_id' => $unsupported->id,
                ]],
                null,
            );
            $this->fail('An unsupported grinding profile must fail.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('checkout.grinding_profile_unsupported', $exception->errorCode);
            $this->assertSame(409, $exception->status);
        }
    }

    /** @return array{Roastery, ProductVariant} */
    private function catalogFixture(): array
    {
        $roastery = Roastery::query()->create([
            'name' => 'روستری R5F',
            'slug' => 'r5f-roastery',
            'city' => 'تهران',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        ShippingRule::query()->create([
            'roastery_id' => $roastery->id,
            'province' => null,
            'city' => null,
            'base_cost' => 100_000,
            'free_over' => null,
            'priority' => 0,
            'is_active' => true,
        ]);
        $origin = Origin::query()->create([
            'name' => 'اتیوپی R5F',
            'slug' => 'r5f-origin',
            'country_code' => 'ETH',
        ]);
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'قهوه R5F',
            'slug' => 'r5f-product',
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'light',
            'arabica_percentage' => 100,
            'tasting_notes' => ['مرکبات'],
            'brewing_suggestions' => [],
            'packaging_fee_mode' => 'free',
            'packaging_fee_amount' => 0,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'R5F-250',
            'weight_grams' => 250,
            'price' => 1_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
        ]);

        return [$roastery, $variant];
    }

    private function profile(string $code, int $version, string $name): GrindingProfile
    {
        return GrindingProfile::query()->firstOrCreate(
            ['code' => $code, 'version' => $version],
            [
                'public_name' => $name,
                'brew_method' => $code,
                'recipe_snapshot' => ['range' => 'operations-owned'],
                'is_active' => true,
            ],
        );
    }
}
