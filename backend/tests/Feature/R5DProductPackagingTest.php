<?php

namespace Tests\Feature;

use App\Exceptions\ApiDomainException;
use App\Models\Address;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\ShippingRule;
use App\Models\User;
use App\Services\Catalog\ProductPackagingPolicy;
use App\Services\Checkout\OrderService;
use App\Services\Checkout\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class R5DProductPackagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_packaging_is_explicitly_snapshotted_and_allocated_per_package(): void
    {
        $customer = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'title' => 'خانه',
            'recipient_name' => 'مشتری بسته‌بندی',
            'recipient_mobile' => '09120000000',
            'province' => 'تهران',
            'city' => 'تهران',
            'address_line' => 'نشانی آزمون R5D',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);

        [$freeRoastery, $freeProduct, $freeVariant] = $this->catalogFixture(
            suffix: 'free',
            price: 1_000_000,
            shipping: 100_000,
        );
        [$paidRoastery, $paidProduct, $paidVariant] = $this->catalogFixture(
            suffix: 'paid',
            price: 2_000_000,
            shipping: 200_000,
        );

        $policy = app(ProductPackagingPolicy::class);
        $freeProduct->forceFill($policy->normalize([
            'packaging_fee_mode' => 'free',
            'packaging_fee_amount' => 999_999,
        ], $freeProduct))->save();
        $paidProduct->forceFill($policy->normalize([
            'packaging_fee_mode' => 'fixed',
            'packaging_fee_amount' => 125_000,
        ], $paidProduct))->save();

        $freeProduct->refresh();
        $paidProduct->refresh();
        $this->assertSame(0, $freeProduct->packagingFee());
        $this->assertTrue($policy->snapshot($freeProduct)['is_free']);
        $this->assertSame(125_000, $paidProduct->packagingFee());
        $this->assertFalse($policy->snapshot($paidProduct)['is_free']);

        $quote = app(QuoteService::class)->createCheckoutQuote(
            $customer,
            $address,
            [
                ['variant_id' => $freeVariant->id, 'quantity' => 2],
                ['variant_id' => $paidVariant->id, 'quantity' => 3],
            ],
            null,
        );
        $quote->load('groups.items.services');

        $this->assertSame(8_000_000, $quote->subtotal);
        $this->assertSame(375_000, $quote->groups->sum('packaging_total'));
        $this->assertSame(300_000, $quote->shipping_total);
        $this->assertSame(8_675_000, $quote->grand_total);
        $this->assertCount(2, $quote->groups);

        $groups = $quote->groups->keyBy('roastery_id');
        $freeGroup = $groups->get($freeRoastery->id);
        $paidGroup = $groups->get($paidRoastery->id);
        $freeService = $freeGroup?->items->first()?->services->first();
        $paidService = $paidGroup?->items->first()?->services->first();

        $this->assertSame(0, $freeGroup?->packaging_total);
        $this->assertSame(375_000, $paidGroup?->packaging_total);
        $this->assertSame(0, $freeService?->packaging_fee);
        $this->assertSame('بسته‌بندی روستری رایگان', $freeService?->service_snapshot['label']);
        $this->assertSame(375_000, $paidService?->packaging_fee);
        $this->assertSame('هزینه بسته‌بندی روستری', $paidService?->service_snapshot['label']);

        $request = Request::create('/api/v1/orders', 'POST');
        $request->headers->set('X-Request-ID', 'r5d-package-test');
        $order = app(OrderService::class)->create(
            $customer,
            $quote->id,
            'r5d:packaging-order:0001',
            null,
            $request,
        );

        $this->assertSame(8_675_000, $order->grand_total);
        $this->assertSame(375_000, $order->subOrders->sum('packaging_total'));
        $this->assertCount(2, $order->subOrders);

        $orderGroups = $order->subOrders->keyBy('roastery_id');
        $freeOrderService = $orderGroups->get($freeRoastery->id)?->items->first()?->services->first();
        $paidOrderService = $orderGroups->get($paidRoastery->id)?->items->first()?->services->first();

        $this->assertSame(0, $freeOrderService?->packaging_fee);
        $this->assertSame('بسته‌بندی روستری رایگان', $freeOrderService?->service_snapshot['label']);
        $this->assertSame(375_000, $paidOrderService?->packaging_fee);

        $this->assertDatabaseCount('checkout_quote_item_services', 2);
        $this->assertDatabaseCount('order_item_services', 2);
        $this->assertDatabaseCount('settlement_allocations', 5);
        $this->assertDatabaseHas('settlement_allocations', [
            'order_id' => $order->id,
            'allocation_type' => 'packaging',
            'owner_type' => 'roastery',
            'owner_id' => $paidRoastery->id,
            'gross_amount' => 375_000,
            'net_amount' => 375_000,
        ]);
    }

    public function test_fixed_packaging_requires_a_positive_amount(): void
    {
        [, $product] = $this->catalogFixture('invalid', 1_000_000, 0);
        $policy = app(ProductPackagingPolicy::class);

        try {
            $policy->normalize([
                'packaging_fee_mode' => 'fixed',
                'packaging_fee_amount' => 0,
            ], $product);
            $this->fail('A fixed packaging policy with zero amount must fail.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('catalog.packaging_amount_required', $exception->errorCode);
            $this->assertSame(422, $exception->status);
        }
    }

    /** @return array{Roastery, Product, ProductVariant} */
    private function catalogFixture(string $suffix, int $price, int $shipping): array
    {
        $roastery = Roastery::query()->create([
            'name' => 'روستری '.$suffix,
            'slug' => 'r5d-roastery-'.$suffix,
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
            'slug' => 'r5d-origin-'.$suffix,
            'country_code' => 'ETH',
        ]);

        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'قهوه '.$suffix,
            'slug' => 'r5d-product-'.$suffix,
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
            'sku' => 'R5D-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => $price,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
        ]);

        return [$roastery, $product, $variant];
    }
}
