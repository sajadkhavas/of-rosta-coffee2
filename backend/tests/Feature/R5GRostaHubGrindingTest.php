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
use App\Models\RostaHub;
use App\Models\RostaHubServiceZone;
use App\Models\ShippingRule;
use App\Models\User;
use App\Services\Checkout\OrderService;
use App\Services\Checkout\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class R5GRostaHubGrindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ineligible_roastery_routes_grinding_through_hub_with_free_packaging_and_rosta_allocations(): void
    {
        $customer = User::factory()->create();
        $address = $this->address($customer, 'تهران', 'تهران');
        [$roastery, $variant] = $this->catalogFixture(packagingFee: 40_000);
        RoasteryGrindingCapability::query()->create([
            'roastery_id' => $roastery->id,
            'availability' => 'unavailable',
            'fee_mode' => 'free',
            'fee_amount' => 0,
            'preparation_minutes' => 0,
            'capacity_per_day' => null,
            'supported_weights' => [],
            'is_active' => true,
        ]);
        $profile = $this->profile();
        [$hub, $zone] = $this->hubFixture($profile);

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
        $quote->load('groups.items.services');

        $group = $quote->groups->firstOrFail();
        $services = $group->items->firstOrFail()->services;
        $roasteryPackaging = $services
            ->where('service_type', 'packaging')
            ->firstWhere('provider_type', 'roastery');
        $hubPackaging = $services
            ->where('service_type', 'packaging')
            ->firstWhere('provider_type', 'rosta_hub');
        $grinding = $services->firstWhere('service_type', 'grinding');

        $this->assertSame(2_000_000, $group->subtotal);
        $this->assertSame(0, $group->packaging_total);
        $this->assertSame(120_000, $group->grinding_total);
        $this->assertSame(100_000, $group->shipping_total);
        $this->assertSame(2_220_000, $group->grand_total);
        $this->assertSame(0, $roasteryPackaging?->packaging_fee);
        $this->assertSame('بسته‌بندی روستری در مسیر هاب: رایگان', $roasteryPackaging?->service_snapshot['label']);
        $this->assertSame(0, $hubPackaging?->packaging_fee);
        $this->assertSame('بسته‌بندی هاب رستا رایگان', $hubPackaging?->service_snapshot['label']);
        $this->assertSame('rosta_hub', $grinding?->provider_type);
        $this->assertSame($hub->id, $grinding?->provider_hub_id);
        $this->assertSame($zone->id, $grinding?->service_snapshot['zone']['id']);
        $this->assertSame(120_000, $grinding?->service_fee);

        $request = Request::create('/api/v1/orders', 'POST');
        $request->headers->set('X-Request-ID', 'r5g-hub-order');
        $order = app(OrderService::class)->create(
            $customer,
            $quote->id,
            'r5g:hub-order:0001',
            null,
            $request,
        );

        $subOrder = $order->subOrders->firstOrFail();
        $orderGrinding = $subOrder->items->firstOrFail()->services
            ->firstWhere('service_type', 'grinding');

        $this->assertSame(2_000_000, $subOrder->payable_total);
        $this->assertSame($hub->id, $orderGrinding?->provider_hub_id);
        $this->assertDatabaseMissing('settlement_allocations', [
            'order_id' => $order->id,
            'allocation_type' => 'packaging',
        ]);
        $this->assertDatabaseHas('settlement_allocations', [
            'order_id' => $order->id,
            'order_item_service_id' => $orderGrinding?->id,
            'allocation_type' => 'grinding',
            'owner_type' => 'rosta',
            'owner_id' => null,
            'gross_amount' => 120_000,
        ]);
        $this->assertSame(2, $subOrder->shipmentLegs()->count());
        $this->assertDatabaseHas('shipment_legs', [
            'order_id' => $order->id,
            'route_type' => 'roastery_to_rosta_hub',
            'gross_amount' => 30_000,
            'charge_owner_type' => 'rosta',
        ]);
        $this->assertDatabaseHas('shipment_legs', [
            'order_id' => $order->id,
            'route_type' => 'rosta_hub_to_customer',
            'gross_amount' => 70_000,
            'charge_owner_type' => 'rosta',
        ]);
        $this->assertSame(100_000, (int) $subOrder->shipmentLegs()->sum('gross_amount'));
        $this->assertSame(100_000, (int) $order->settlementAllocations()
            ->where('allocation_type', 'shipping')
            ->where('owner_type', 'rosta')
            ->sum('gross_amount'));
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'hub.route_planned',
        ]);

        $variant->refresh();
        $this->assertSame(250, $variant->weight_grams);
        $this->assertSame(10, $variant->stock_on_hand);
        $this->assertSame(2, $variant->stock_reserved);
    }

    public function test_hub_grinding_is_rejected_outside_enabled_tehran_or_karaj_zone(): void
    {
        $customer = User::factory()->create();
        $address = $this->address($customer, 'اصفهان', 'اصفهان');
        [$roastery, $variant] = $this->catalogFixture();
        RoasteryGrindingCapability::query()->create([
            'roastery_id' => $roastery->id,
            'availability' => 'unavailable',
            'fee_mode' => 'free',
            'fee_amount' => 0,
            'preparation_minutes' => 0,
            'capacity_per_day' => null,
            'supported_weights' => [],
            'is_active' => true,
        ]);
        $profile = $this->profile();
        $this->hubFixture($profile);

        try {
            app(QuoteService::class)->createCheckoutQuote(
                $customer,
                $address,
                [[
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'grinding_profile_id' => $profile->id,
                ]],
                null,
            );
            $this->fail('An address outside enabled Hub zones must fail.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('hub_zone_ineligible', $exception->errorCode);
            $this->assertSame(409, $exception->status);
        }
    }

    public function test_available_roastery_remains_authoritative_and_hub_is_not_used(): void
    {
        $customer = User::factory()->create();
        $address = $this->address($customer, 'تهران', 'تهران');
        [$roastery, $variant] = $this->catalogFixture();
        $profile = $this->profile();
        $capability = RoasteryGrindingCapability::query()->create([
            'roastery_id' => $roastery->id,
            'availability' => 'available',
            'fee_mode' => 'free',
            'fee_amount' => 0,
            'preparation_minutes' => 15,
            'capacity_per_day' => null,
            'supported_weights' => [250],
            'is_active' => true,
        ]);
        $capability->profiles()->sync([$profile->id]);
        $this->hubFixture($profile);

        $quote = app(QuoteService::class)->createCheckoutQuote(
            $customer,
            $address,
            [[
                'variant_id' => $variant->id,
                'quantity' => 1,
                'grinding_profile_id' => $profile->id,
            ]],
            null,
        );
        $quote->load('groups.items.services');
        $grinding = $quote->groups->firstOrFail()->items->firstOrFail()->services
            ->firstWhere('service_type', 'grinding');

        $this->assertSame('roastery', $grinding?->provider_type);
        $this->assertSame($roastery->id, $grinding?->provider_roastery_id);
        $this->assertNull($grinding?->provider_hub_id);
    }

    private function address(User $user, string $province, string $city): Address
    {
        return Address::query()->create([
            'user_id' => $user->id,
            'title' => 'خانه',
            'recipient_name' => 'مشتری R5G',
            'recipient_mobile' => '09120000000',
            'province' => $province,
            'city' => $city,
            'address_line' => 'نشانی آزمون R5G',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);
    }

    /** @return array{Roastery, ProductVariant} */
    private function catalogFixture(int $packagingFee = 0): array
    {
        $roastery = Roastery::query()->create([
            'name' => 'روستری R5G',
            'slug' => 'r5g-roastery-'.str()->random(8),
            'city' => 'تهران',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        ShippingRule::query()->create([
            'roastery_id' => $roastery->id,
            'province' => null,
            'city' => null,
            'base_cost' => 90_000,
            'free_over' => null,
            'priority' => 0,
            'is_active' => true,
        ]);
        $origin = Origin::query()->create([
            'name' => 'اتیوپی R5G '.str()->random(5),
            'slug' => 'r5g-origin-'.str()->random(8),
            'country_code' => 'ETH',
        ]);
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'قهوه R5G',
            'slug' => 'r5g-product-'.str()->random(8),
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'light',
            'arabica_percentage' => 100,
            'tasting_notes' => ['مرکبات'],
            'brewing_suggestions' => [],
            'packaging_fee_mode' => $packagingFee > 0 ? 'fixed' : 'free',
            'packaging_fee_amount' => $packagingFee,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'R5G-'.str()->upper(str()->random(8)),
            'weight_grams' => 250,
            'price' => 1_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
        ]);

        return [$roastery, $variant];
    }

    private function profile(): GrindingProfile
    {
        return GrindingProfile::query()->firstOrCreate(
            ['code' => 'v60', 'version' => 2],
            [
                'public_name' => 'V60',
                'brew_method' => 'v60',
                'recipe_snapshot' => ['range' => 'operations-owned'],
                'is_active' => true,
            ],
        );
    }

    /** @return array{RostaHub, RostaHubServiceZone} */
    private function hubFixture(GrindingProfile $profile): array
    {
        $hub = RostaHub::query()->create([
            'code' => 'tehran-central-'.str()->random(6),
            'name' => 'هاب مرکزی رستا',
            'province' => 'تهران',
            'city' => 'تهران',
            'fee_mode' => 'fixed',
            'fee_amount' => 60_000,
            'preparation_minutes' => 30,
            'capacity_per_day' => 100,
            'supported_weights' => [250, 500],
            'is_active' => true,
        ]);
        $hub->profiles()->sync([$profile->id]);
        $zone = RostaHubServiceZone::query()->create([
            'hub_id' => $hub->id,
            'province' => 'تهران',
            'city' => 'تهران',
            'inbound_shipping_fee' => 30_000,
            'outbound_shipping_fee' => 70_000,
            'priority' => 100,
            'is_active' => true,
        ]);

        return [$hub, $zone];
    }
}
