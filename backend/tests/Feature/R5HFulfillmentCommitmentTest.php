<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\Role;
use App\Enums\SettlementAllocationStatus;
use App\Models\CheckoutQuote;
use App\Models\InventoryRestock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Origin;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundAttempt;
use App\Models\Roastery;
use App\Models\SettlementAllocation;
use App\Models\StockLedgerEntry;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentSlaMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class R5HFulfillmentCommitmentTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rosta.notifications.enabled' => false,
            'rosta.notifications.sms_provider' => 'disabled',
            'rosta.refund.enabled' => true,
            'rosta.refund.provider' => 'manual',
            'rosta.refund.require_dual_control' => false,
        ]);
    }

    public function test_admin_exception_cancels_and_refunds_only_the_affected_sub_order(): void
    {
        [$seller, $administrator, $firstRoastery, $order, $firstSubOrder, $secondSubOrder, $firstVariant, $secondVariant] = $this->fixture();

        $this->authenticateWithRole(
            $seller,
            Role::RoasteryOwner,
            'roastery',
            $firstRoastery->id,
        );
        $incidentResponse = $this->postJson(
            "/api/v1/seller/roasteries/{$firstRoastery->id}/orders/{$order->id}/incidents",
            [
                'code' => 'inventory_mismatch',
                'description' => 'موجودی فیزیکی این بچ با موجودی ثبت‌شده تطبیق ندارد.',
                'severity' => 'critical',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.status', 'accepted');
        $incidentId = (string) $incidentResponse->json('data.sub_orders.0.incidents.0.id');

        $this->authenticateWithRole($administrator, Role::Administrator);
        $this->postJson("/api/v1/admin/fulfillment-incidents/{$incidentId}/resolve", [
            'resolution' => 'cancel_and_refund',
            'note' => 'عدم امکان تأمین تأیید شد؛ فقط همین زیرسفارش بازپرداخت شود.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_cancelled');

        $order->refresh();
        $firstSubOrder->refresh();
        $secondSubOrder->refresh();
        $firstVariant->refresh();
        $secondVariant->refresh();

        $this->assertSame(OrderStatus::PartiallyCancelled, $order->status);
        $this->assertSame('refund_pending', $firstSubOrder->status->value);
        $this->assertSame('accepted', $secondSubOrder->status->value);
        $this->assertSame(5, $firstVariant->stock_on_hand);
        $this->assertSame(4, $secondVariant->stock_on_hand);
        $this->assertSame(1, InventoryRestock::query()
            ->where('order_item_id', $firstSubOrder->items()->value('id'))
            ->count());
        $this->assertSame(1, StockLedgerEntry::query()
            ->where('reference_type', 'sub_order')
            ->where('reference_id', $firstSubOrder->id)
            ->count());
        $this->assertDatabaseHas('settlement_allocations', [
            'sub_order_id' => $firstSubOrder->id,
            'status' => 'reversed',
        ]);
        $this->assertDatabaseHas('settlement_allocations', [
            'sub_order_id' => $secondSubOrder->id,
            'status' => 'held',
        ]);
        $this->assertDatabaseHas('refund_attempts', [
            'order_id' => $order->id,
            'amount' => 4_300_000,
            'status' => 'requested',
        ]);
        $refund = RefundAttempt::query()->where('order_id', $order->id)->sole();
        $this->assertDatabaseHas('fulfillment_incidents', [
            'id' => $incidentId,
            'status' => 'resolved',
            'resolution' => 'cancel_and_refund',
            'refund_attempt_id' => $refund->id,
        ]);

        $this->postJson("/api/v1/admin/fulfillment-incidents/{$incidentId}/resolve", [
            'resolution' => 'cancel_and_refund',
            'note' => 'تلاش تکراری برای لغو',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'fulfillment.incident_already_resolved');
        $this->assertSame(1, InventoryRestock::query()->count());
        $this->assertSame(1, RefundAttempt::query()->count());
    }

    public function test_sla_monitor_marks_breach_once_without_touching_healthy_siblings(): void
    {
        [, , , , $firstSubOrder, $secondSubOrder] = $this->fixture();
        $firstSubOrder->forceFill([
            'preparation_due_at' => now()->subMinute(),
            'sla_status' => 'on_track',
        ])->save();

        $monitor = app(FulfillmentSlaMonitorService::class);
        $this->assertSame(1, $monitor->markBreaches());
        $this->assertSame('breached', $firstSubOrder->fresh()->sla_status->value);
        $this->assertSame('on_track', $secondSubOrder->fresh()->sla_status->value);
        $this->assertDatabaseCount('order_events', 1);
        $this->assertDatabaseHas('order_events', [
            'sub_order_id' => $firstSubOrder->id,
            'event_type' => 'fulfillment.sla_breached',
        ]);

        $this->assertSame(0, $monitor->markBreaches());
        $this->assertDatabaseCount('order_events', 1);
    }

    /**
     * @return array{User, User, Roastery, Order, SubOrder, SubOrder, ProductVariant, ProductVariant}
     */
    private function fixture(): array
    {
        $seller = User::factory()->create();
        $administrator = User::factory()->create();
        $customer = User::factory()->create();
        $origin = Origin::query()->create([
            'name' => 'اتیوپی R5H',
            'slug' => 'r5h-origin',
            'country_code' => 'ETH',
        ]);
        $firstRoastery = $this->roastery('اول', 'r5h-first-roastery');
        $secondRoastery = $this->roastery('دوم', 'r5h-second-roastery');
        [$firstProduct, $firstVariant] = $this->product(
            $firstRoastery,
            $origin,
            'اول',
            'R5H-FIRST',
            3,
            2_000_000,
        );
        [$secondProduct, $secondVariant] = $this->product(
            $secondRoastery,
            $origin,
            'دوم',
            'R5H-SECOND',
            4,
            2_000_000,
        );

        $quote = CheckoutQuote::query()->create([
            'user_id' => $customer->id,
            'address_id' => null,
            'roastery_id' => null,
            'coupon_id' => null,
            'purpose' => 'checkout',
            'payload_hash' => hash('sha256', 'r5h-multi-order'),
            'subtotal' => 6_000_000,
            'shipping_total' => 600_000,
            'discount_total' => 0,
            'grand_total' => 6_600_000,
            'currency' => 'IRR',
            'address_snapshot' => [
                'recipient_name' => 'مشتری R5H',
                'recipient_mobile' => '09120000000',
                'province' => 'تهران',
                'city' => 'تهران',
                'address_line' => 'نشانی تست R5H',
                'postal_code' => '1234567890',
            ],
            'shipping_snapshot' => [],
            'warnings' => [],
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => now(),
        ]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'roastery_id' => null,
            'quote_id' => $quote->id,
            'order_number' => 'R-R5H-MULTI',
            'status' => OrderStatus::Paid,
            'address_snapshot' => $quote->address_snapshot,
            'subtotal' => 6_000_000,
            'shipping_total' => 600_000,
            'discount_total' => 0,
            'grand_total' => 6_600_000,
            'currency' => 'IRR',
            'placed_at' => now()->subMinute(),
            'paid_at' => now(),
        ]);
        $firstSubOrder = $this->subOrder($order, $firstRoastery, 4_000_000, 300_000);
        $secondSubOrder = $this->subOrder($order, $secondRoastery, 2_000_000, 300_000);
        $firstItem = $this->item($order, $firstSubOrder, $firstProduct, $firstVariant, 2);
        $secondItem = $this->item($order, $secondSubOrder, $secondProduct, $secondVariant, 1);
        $this->allocation($order, $firstSubOrder, $firstItem, $firstRoastery, 4_000_000, 'first');
        $this->allocation($order, $secondSubOrder, $secondItem, $secondRoastery, 2_000_000, 'second');

        PaymentAttempt::query()->create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'testing',
            'attempt_number' => 1,
            'idempotency_key' => 'r5h-payment-attempt',
            'request_hash' => hash('sha256', 'r5h-payment-attempt'),
            'status' => PaymentAttemptStatus::Verified,
            'amount' => 6_600_000,
            'amount_provider' => 6_600_000,
            'currency' => 'IRR',
            'reference_id' => 'R5H-PAYMENT-REFERENCE',
            'request_payload' => [],
            'response_payload' => [],
            'verification_payload' => [],
            'verified_at' => now(),
        ]);

        return [
            $seller,
            $administrator,
            $firstRoastery,
            $order,
            $firstSubOrder,
            $secondSubOrder,
            $firstVariant,
            $secondVariant,
        ];
    }

    private function roastery(string $name, string $slug): Roastery
    {
        return Roastery::query()->create([
            'name' => 'روستری '.$name,
            'slug' => $slug,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    /** @return array{Product, ProductVariant} */
    private function product(
        Roastery $roastery,
        Origin $origin,
        string $name,
        string $sku,
        int $stock,
        int $price,
    ): array {
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'قهوه '.$name,
            'slug' => 'r5h-product-'.strtolower($sku),
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
            'sku' => $sku,
            'weight_grams' => 250,
            'price' => $price,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => $stock,
            'stock_reserved' => 0,
        ]);

        return [$product, $variant];
    }

    private function subOrder(
        Order $order,
        Roastery $roastery,
        int $subtotal,
        int $shipping,
    ): SubOrder {
        return SubOrder::query()->create([
            'order_id' => $order->id,
            'roastery_id' => $roastery->id,
            'status' => 'accepted',
            'acceptance_status' => 'accepted',
            'subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'packaging_total' => 0,
            'grinding_total' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $subtotal + $shipping,
            'commission_total' => 0,
            'payable_total' => $subtotal + $shipping,
            'currency' => 'IRR',
            'accepted_at' => now(),
            'fulfillment_committed_at' => now(),
            'preparation_due_at' => now()->addDay(),
            'handoff_due_at' => now()->addDays(2),
            'sla_status' => 'on_track',
        ]);
    }

    private function item(
        Order $order,
        SubOrder $subOrder,
        Product $product,
        ProductVariant $variant,
        int $quantity,
    ): OrderItem {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'roast_batch_id' => null,
            'quantity' => $quantity,
            'unit_price' => $variant->price,
            'line_total' => $variant->price * $quantity,
            'product_snapshot' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'primary_image' => null,
            ],
            'variant_snapshot' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'weight_grams' => $variant->weight_grams,
                'price' => $variant->price,
                'currency' => 'IRR',
            ],
            'roast_batch_snapshot' => null,
        ]);
    }

    private function allocation(
        Order $order,
        SubOrder $subOrder,
        OrderItem $item,
        Roastery $roastery,
        int $amount,
        string $suffix,
    ): void {
        SettlementAllocation::query()->create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'order_item_id' => $item->id,
            'allocation_type' => 'product',
            'owner_type' => 'roastery',
            'owner_id' => $roastery->id,
            'status' => SettlementAllocationStatus::Held,
            'gross_amount' => $amount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $amount,
            'currency' => 'IRR',
            'pricing_version' => 'r5h-test-v1',
            'source_reference' => 'r5h-test-'.$suffix,
            'idempotency_key' => 'r5h-test-allocation-'.$suffix,
            'metadata' => [],
        ]);
    }
}
