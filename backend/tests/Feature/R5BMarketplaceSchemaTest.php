<?php

namespace Tests\Feature;

use App\Enums\GrindingAvailability;
use App\Enums\OrderItemServiceStatus;
use App\Enums\OrderStatus;
use App\Enums\SettlementAllocationStatus;
use App\Enums\ShipmentLegStatus;
use App\Enums\SubOrderAcceptanceStatus;
use App\Enums\SubOrderStatus;
use App\Models\CheckoutQuote;
use App\Models\CheckoutQuoteGroup;
use App\Models\CheckoutQuoteItem;
use App\Models\CheckoutQuoteItemService;
use App\Models\GrindingProfile;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\OrderItemService;
use App\Models\OrderTaxLine;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\RoasteryGrindingCapability;
use App\Models\SettlementAllocation;
use App\Models\ShipmentLeg;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class R5BMarketplaceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_order_supports_independent_roastery_sub_orders(): void
    {
        $fixture = $this->fixture();

        $order = $fixture['order']->fresh(['subOrders', 'quote.groups']);
        $pending = $order->subOrders->firstWhere(
            'acceptance_status',
            SubOrderAcceptanceStatus::AwaitingRoasteryAcceptance,
        );
        $accepted = $order->subOrders->firstWhere(
            'acceptance_status',
            SubOrderAcceptanceStatus::Accepted,
        );

        $this->assertNull($order->roastery_id);
        $this->assertCount(2, $order->subOrders);
        $this->assertCount(2, $order->quote->groups);
        $this->assertNotNull($pending);
        $this->assertNotNull($accepted);
        $this->assertTrue($pending->isCustomerCancellable());
        $this->assertFalse($accepted->isCustomerCancellable());
        $this->assertSame(0, $pending->packaging_total);
        $this->assertSame('IRR', $accepted->currency);
    }

    public function test_grinding_shipping_settlement_tax_and_tracking_are_child_scoped(): void
    {
        $fixture = $this->fixture();
        $order = $fixture['order'];
        $subOrder = $fixture['sub_orders'][1];
        $item = $fixture['items'][1];
        $roastery = $fixture['roasteries'][1];

        $profile = GrindingProfile::query()->create([
            'code' => 'v60',
            'version' => 1,
            'public_name' => 'V60',
            'brew_method' => 'v60',
            'recipe_snapshot' => [
                'range' => 'medium-fine',
                'contract' => 'service_only',
            ],
            'is_active' => true,
        ]);

        $capability = RoasteryGrindingCapability::query()->create([
            'roastery_id' => $roastery->id,
            'availability' => GrindingAvailability::Unavailable,
            'fee_mode' => 'free',
            'fee_amount' => 0,
            'preparation_minutes' => 0,
            'capacity_per_day' => null,
            'supported_weights' => [50, 100, 250, 500, 1000],
            'is_active' => true,
        ]);
        $capability->profiles()->attach($profile->id);

        CheckoutQuoteItemService::query()->create([
            'quote_group_id' => $fixture['quote_groups'][1]->id,
            'quote_item_id' => $fixture['quote_items'][1]->id,
            'service_type' => 'grinding',
            'provider_type' => 'rosta_hub',
            'provider_roastery_id' => null,
            'grinding_profile_id' => $profile->id,
            'service_fee' => 80_000,
            'packaging_fee' => 0,
            'shipping_fee' => 120_000,
            'tax_amount' => 0,
            'total_amount' => 200_000,
            'currency' => 'IRR',
            'pricing_snapshot' => ['version' => 'r5b-test'],
            'service_snapshot' => ['hub_packaging' => 'free'],
        ]);

        $service = OrderItemService::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'order_item_id' => $item->id,
            'service_type' => 'grinding',
            'provider_type' => 'rosta_hub',
            'provider_roastery_id' => null,
            'grinding_profile_id' => $profile->id,
            'status' => OrderItemServiceStatus::Requested,
            'service_fee' => 80_000,
            'packaging_fee' => 0,
            'shipping_fee' => 120_000,
            'tax_amount' => 0,
            'total_amount' => 200_000,
            'currency' => 'IRR',
            'pricing_snapshot' => ['version' => 'r5b-test'],
            'service_snapshot' => ['hub_packaging' => 'free'],
        ]);

        $leg = ShipmentLeg::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'order_item_service_id' => $service->id,
            'route_type' => 'roastery_to_rosta_hub',
            'sequence' => 1,
            'status' => ShipmentLegStatus::Planned,
            'charge_owner_type' => 'rosta',
            'charge_owner_id' => null,
            'gross_amount' => 120_000,
            'tax_amount' => 0,
            'total_amount' => 120_000,
            'currency' => 'IRR',
            'origin_snapshot' => ['type' => 'roastery'],
            'destination_snapshot' => ['type' => 'rosta_hub', 'city' => 'کرج'],
            'planned_at' => now(),
        ]);

        $allocation = SettlementAllocation::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'order_item_id' => $item->id,
            'order_item_service_id' => $service->id,
            'shipment_leg_id' => $leg->id,
            'allocation_type' => 'rosta_hub_grinding',
            'owner_type' => 'rosta',
            'owner_id' => null,
            'status' => SettlementAllocationStatus::Held,
            'gross_amount' => 200_000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => 200_000,
            'currency' => 'IRR',
            'tax_code' => 'service_pending_classification',
            'pricing_version' => 'r5b-test',
            'source_reference' => $service->id,
            'idempotency_key' => 'r5b-allocation-'.$service->id,
            'metadata' => ['settlement_owner' => 'rosta'],
        ]);

        OrderTaxLine::query()->create([
            'settlement_allocation_id' => $allocation->id,
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'order_item_service_id' => $service->id,
            'tax_code' => 'service_pending_classification',
            'jurisdiction' => 'IR',
            'taxable_amount' => 200_000,
            'tax_amount' => 0,
            'rate_basis_points' => null,
            'calculation_snapshot' => ['status' => 'requires_accountant_confirmation'],
        ]);

        $event = OrderEvent::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'order_item_id' => $item->id,
            'order_item_service_id' => $service->id,
            'shipment_leg_id' => $leg->id,
            'event_type' => 'grinding.requested',
            'previous_state' => null,
            'next_state' => 'requested',
            'actor_type' => 'system',
            'request_id' => 'r5b-test-request',
            'customer_title' => 'درخواست آسیاب ثبت شد',
            'customer_description' => 'این روستری آسیاب ندارد و درخواست برای مرکز رستا ثبت شد.',
            'internal_metadata' => ['private_contract' => 'r5b'],
            'occurred_at' => now(),
        ]);

        $service->refresh();
        $this->assertSame(OrderItemServiceStatus::Requested, $service->status);
        $this->assertSame(0, $service->packaging_fee);
        $this->assertSame('rosta', $allocation->owner_type);
        $this->assertSame(SettlementAllocationStatus::Held, $allocation->status);
        $this->assertSame(ShipmentLegStatus::Planned, $leg->status);
        $this->assertCount(1, $order->fresh()->shipmentLegs);
        $this->assertCount(1, $subOrder->fresh()->itemServices);
        $this->assertCount(1, $allocation->fresh()->taxLines);
        $this->assertCount(1, $capability->fresh()->profiles);

        $this->expectException(LogicException::class);
        $event->update(['customer_title' => 'نباید تغییر کند']);
    }

    public function test_order_events_are_append_only(): void
    {
        $fixture = $this->fixture();

        $event = OrderEvent::query()->create([
            'order_id' => $fixture['order']->id,
            'sub_order_id' => $fixture['sub_orders'][0]->id,
            'event_type' => 'sub_order.awaiting_acceptance',
            'previous_state' => null,
            'next_state' => 'awaiting_roastery_acceptance',
            'actor_type' => 'system',
            'customer_title' => 'در انتظار پذیرش روستری',
            'occurred_at' => now(),
        ]);

        try {
            $event->delete();
            $this->fail('Append-only order event deletion must fail.');
        } catch (LogicException) {
            $this->assertDatabaseHas('order_events', ['id' => $event->id]);
        }
    }

    /**
     * @return array{
     *   order: Order,
     *   roasteries: array{Roastery, Roastery},
     *   sub_orders: array{SubOrder, SubOrder},
     *   items: array{OrderItem, OrderItem},
     *   quote_groups: array{CheckoutQuoteGroup, CheckoutQuoteGroup},
     *   quote_items: array{CheckoutQuoteItem, CheckoutQuoteItem}
     * }
     */
    private function fixture(): array
    {
        $customer = User::factory()->create();
        $roasteries = [
            $this->roastery('alpha', 'تهران'),
            $this->roastery('beta', 'کرج'),
        ];
        $products = [
            $this->product($roasteries[0], 'alpha'),
            $this->product($roasteries[1], 'beta'),
        ];

        $quote = CheckoutQuote::query()->create([
            'user_id' => $customer->id,
            'address_id' => null,
            'roastery_id' => null,
            'coupon_id' => null,
            'purpose' => 'checkout',
            'payload_hash' => hash('sha256', 'r5b-marketplace-quote'),
            'subtotal' => 5_000_000,
            'shipping_total' => 300_000,
            'discount_total' => 0,
            'grand_total' => 5_300_000,
            'currency' => 'IRR',
            'address_snapshot' => [
                'recipient_name' => 'مشتری R5B',
                'recipient_mobile' => '09120000000',
                'province' => 'البرز',
                'city' => 'کرج',
                'address_line' => 'نشانی تست',
                'postal_code' => '1234567890',
            ],
            'shipping_snapshot' => ['mode' => 'multi_roastery'],
            'warnings' => [],
            'expires_at' => now()->addMinutes(15),
        ]);

        $quoteGroups = [
            CheckoutQuoteGroup::query()->create([
                'quote_id' => $quote->id,
                'roastery_id' => $roasteries[0]->id,
                'subtotal' => 2_000_000,
                'packaging_total' => 0,
                'grinding_total' => 0,
                'shipping_total' => 100_000,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 2_100_000,
                'currency' => 'IRR',
                'pricing_snapshot' => ['packaging' => 'free'],
            ]),
            CheckoutQuoteGroup::query()->create([
                'quote_id' => $quote->id,
                'roastery_id' => $roasteries[1]->id,
                'subtotal' => 3_000_000,
                'packaging_total' => 0,
                'grinding_total' => 0,
                'shipping_total' => 200_000,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 3_200_000,
                'currency' => 'IRR',
                'pricing_snapshot' => ['packaging' => 'free'],
            ]),
        ];

        $quoteItems = [
            $this->quoteItem($quote, $quoteGroups[0], $products[0], 2_000_000),
            $this->quoteItem($quote, $quoteGroups[1], $products[1], 3_000_000),
        ];

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'roastery_id' => null,
            'quote_id' => $quote->id,
            'order_number' => 'R-R5B-MULTI',
            'status' => OrderStatus::Paid,
            'address_snapshot' => $quote->address_snapshot,
            'subtotal' => 5_000_000,
            'shipping_total' => 300_000,
            'discount_total' => 0,
            'grand_total' => 5_300_000,
            'currency' => 'IRR',
            'placed_at' => now(),
            'paid_at' => now(),
        ]);

        $subOrders = [
            SubOrder::query()->create([
                'order_id' => $order->id,
                'roastery_id' => $roasteries[0]->id,
                'status' => SubOrderStatus::PendingAcceptance,
                'acceptance_status' => SubOrderAcceptanceStatus::AwaitingRoasteryAcceptance,
                'subtotal' => 2_000_000,
                'shipping_total' => 100_000,
                'packaging_total' => 0,
                'grinding_total' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 2_100_000,
                'commission_total' => 0,
                'payable_total' => 2_000_000,
                'currency' => 'IRR',
            ]),
            SubOrder::query()->create([
                'order_id' => $order->id,
                'roastery_id' => $roasteries[1]->id,
                'status' => SubOrderStatus::Accepted,
                'acceptance_status' => SubOrderAcceptanceStatus::Accepted,
                'subtotal' => 3_000_000,
                'shipping_total' => 200_000,
                'packaging_total' => 0,
                'grinding_total' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 3_200_000,
                'commission_total' => 0,
                'payable_total' => 3_000_000,
                'currency' => 'IRR',
                'accepted_at' => now(),
            ]),
        ];

        $items = [
            $this->orderItem($order, $subOrders[0], $products[0], 2_000_000),
            $this->orderItem($order, $subOrders[1], $products[1], 3_000_000),
        ];

        return [
            'order' => $order,
            'roasteries' => $roasteries,
            'sub_orders' => $subOrders,
            'items' => $items,
            'quote_groups' => $quoteGroups,
            'quote_items' => $quoteItems,
        ];
    }

    private function roastery(string $suffix, string $city): Roastery
    {
        return Roastery::query()->create([
            'name' => 'روستری R5B '.$suffix,
            'slug' => 'r5b-roastery-'.$suffix,
            'city' => $city,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    /** @return array{Product, ProductVariant} */
    private function product(Roastery $roastery, string $suffix): array
    {
        $origin = Origin::query()->create([
            'name' => 'خاستگاه R5B '.$suffix,
            'slug' => 'r5b-origin-'.$suffix,
            'country_code' => 'ETH',
        ]);
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'قهوه R5B '.$suffix,
            'slug' => 'r5b-product-'.$suffix,
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
            'sku' => 'R5B-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => $suffix === 'alpha' ? 2_000_000 : 3_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
        ]);

        return [$product, $variant];
    }

    /** @param array{Product, ProductVariant} $product */
    private function quoteItem(
        CheckoutQuote $quote,
        CheckoutQuoteGroup $group,
        array $product,
        int $price,
    ): CheckoutQuoteItem {
        [$coffee, $variant] = $product;

        return CheckoutQuoteItem::query()->create([
            'quote_id' => $quote->id,
            'group_id' => $group->id,
            'product_id' => $coffee->id,
            'variant_id' => $variant->id,
            'roast_batch_id' => null,
            'quantity' => 1,
            'unit_price' => $price,
            'compare_at_price' => null,
            'line_total' => $price,
            'product_snapshot' => ['id' => $coffee->id, 'name' => $coffee->name],
            'variant_snapshot' => [
                'id' => $variant->id,
                'weight_grams' => 250,
                'price' => $price,
                'currency' => 'IRR',
            ],
            'roast_batch_snapshot' => null,
        ]);
    }

    /** @param array{Product, ProductVariant} $product */
    private function orderItem(
        Order $order,
        SubOrder $subOrder,
        array $product,
        int $price,
    ): OrderItem {
        [$coffee, $variant] = $product;

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'product_id' => $coffee->id,
            'variant_id' => $variant->id,
            'roast_batch_id' => null,
            'quantity' => 1,
            'unit_price' => $price,
            'line_total' => $price,
            'product_snapshot' => ['id' => $coffee->id, 'name' => $coffee->name],
            'variant_snapshot' => [
                'id' => $variant->id,
                'weight_grams' => 250,
                'price' => $price,
                'currency' => 'IRR',
            ],
            'roast_batch_snapshot' => null,
        ]);
    }
}
