<?php

namespace Tests\Feature\Growth;

use App\Enums\Role;
use App\Models\Address;
use App\Models\GrowthPartner;
use App\Models\Order;
use App\Models\Origin;
use App\Models\PartnerAttribution;
use App\Models\PartnerCommissionEntry;
use App\Models\PartnerCommissionEvent;
use App\Models\PartnerCommissionPolicy;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundAttempt;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\ShippingRule;
use App\Models\User;
use App\Services\Growth\PartnerCommissionEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class PartnerCommissionLifecycleTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rosta.payment.enabled' => true,
            'rosta.payment.provider' => 'testing',
            'rosta.payment.amount_multiplier' => 1,
            'rosta.payment.frontend_callback_url' => 'http://localhost:5173/checkout',
            'rosta.allowed_payment_redirect_hosts' => ['localhost', '127.0.0.1'],
            'rosta.refund.enabled' => true,
            'rosta.refund.provider' => 'testing',
            'rosta.refund.require_dual_control' => true,
            'rosta.notifications.enabled' => false,
            'rosta.notifications.sms_provider' => 'disabled',
        ]);
    }

    public function test_missing_policy_blocks_commission_event_without_rolling_back_paid_order(): void
    {
        [$customer, $address, $variant] = $this->fixture('blocked');
        $this->authenticateWithRole($customer, Role::Customer);
        $order = $this->createOrder($address, $variant, 'blocked');
        $this->attributeOrder((string) $order['id'], 'blocked');

        $payment = $this->payOrder((string) $order['id'], 'blocked');

        $this->assertDatabaseHas('orders', [
            'id' => $order['id'],
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('partner_commission_events', [
            'event_type' => PartnerCommissionEvent::EVENT_ORDER_PAID,
            'source_id' => $order['id'],
            'status' => PartnerCommissionEvent::STATUS_PENDING,
        ]);

        $result = app(PartnerCommissionEventService::class)->processDue();

        self::assertSame(1, $result['blocked']);
        $this->assertDatabaseHas('orders', [
            'id' => $order['id'],
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('partner_commission_events', [
            'event_type' => PartnerCommissionEvent::EVENT_ORDER_PAID,
            'source_id' => $order['id'],
            'status' => PartnerCommissionEvent::STATUS_BLOCKED,
            'last_error_code' => 'growth.commission_policy_missing',
        ]);
        $this->assertDatabaseCount('partner_commission_entries', 0);

        $this->get('/api/v1/payments/testing/callback/'.$payment['payment_id'].'?status=OK')->assertRedirect();
        $this->postJson('/api/v1/payments/'.$payment['payment_id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        self::assertSame(1, PartnerCommissionEvent::query()
            ->where('event_type', PartnerCommissionEvent::EVENT_ORDER_PAID)
            ->where('source_id', $order['id'])
            ->count());
    }

    public function test_published_policy_processes_exactly_one_accrual_across_payment_retries(): void
    {
        [$customer, $address, $variant] = $this->fixture('accrual');
        $this->authenticateWithRole($customer, Role::Customer);
        $order = $this->createOrder($address, $variant, 'accrual');
        $this->attributeOrder((string) $order['id'], 'accrual');
        $this->publishPolicy('accrual');

        $payment = $this->payOrder((string) $order['id'], 'accrual');
        $result = app(PartnerCommissionEventService::class)->processDue();

        self::assertSame(1, $result['processed']);
        $this->assertDatabaseHas('partner_commission_events', [
            'event_type' => PartnerCommissionEvent::EVENT_ORDER_PAID,
            'source_id' => $order['id'],
            'status' => PartnerCommissionEvent::STATUS_PROCESSED,
        ]);
        $this->assertDatabaseHas('partner_commission_entries', [
            'order_id' => $order['id'],
            'entry_type' => PartnerCommissionEntry::TYPE_ACCRUAL,
            'source_type' => 'order_payment',
            'source_id' => $order['id'],
        ]);
        $this->assertDatabaseCount('partner_commission_entries', 1);

        $this->get('/api/v1/payments/testing/callback/'.$payment['payment_id'].'?status=OK')->assertRedirect();
        $this->postJson('/api/v1/payments/'.$payment['payment_id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
        $second = app(PartnerCommissionEventService::class)->processDue();

        self::assertSame(0, $second['processed']);
        self::assertSame(1, PartnerCommissionEvent::query()
            ->where('event_type', PartnerCommissionEvent::EVENT_ORDER_PAID)
            ->where('source_id', $order['id'])
            ->count());
        $this->assertDatabaseCount('partner_commission_entries', 1);
    }

    public function test_successful_refund_creates_one_append_only_reversal_across_dispatch_retries(): void
    {
        [$customer, $address, $variant] = $this->fixture('refund');
        $this->authenticateWithRole($customer, Role::Customer);
        $order = $this->createOrder($address, $variant, 'refund');
        $this->attributeOrder((string) $order['id'], 'refund');
        $this->publishPolicy('refund');
        $this->payOrder((string) $order['id'], 'refund');
        app(PartnerCommissionEventService::class)->processDue();

        $accrual = PartnerCommissionEntry::query()
            ->where('order_id', $order['id'])
            ->where('entry_type', PartnerCommissionEntry::TYPE_ACCRUAL)
            ->firstOrFail();
        $originalCommission = $accrual->commission_amount;

        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $this->authenticateWithRole($requester, Role::Administrator);
        $refund = $this->postJson('/api/v1/admin/orders/'.$order['id'].'/refunds', [
            'amount' => $order['grand_total'],
            'reason' => 'Partner commission reversal test',
            'idempotency_key' => 'growth:refund:refund:000001',
        ])->assertCreated()->json('data');

        $this->authenticateWithRole($approver, Role::Administrator);
        $this->postJson('/api/v1/admin/refunds/'.$refund['id'].'/approve')->assertOk();
        $this->postJson('/api/v1/admin/refunds/'.$refund['id'].'/dispatch')
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertDatabaseHas('partner_commission_events', [
            'event_type' => PartnerCommissionEvent::EVENT_REFUND_SUCCEEDED,
            'source_id' => $refund['id'],
            'status' => PartnerCommissionEvent::STATUS_PENDING,
        ]);

        $result = app(PartnerCommissionEventService::class)->processDue();
        self::assertSame(1, $result['processed']);

        $reversal = PartnerCommissionEntry::query()
            ->where('order_id', $order['id'])
            ->where('entry_type', PartnerCommissionEntry::TYPE_REVERSAL)
            ->firstOrFail();
        self::assertSame($accrual->id, $reversal->reversal_of_id);
        self::assertLessThanOrEqual(0, $reversal->commission_amount);
        self::assertSame($originalCommission, $accrual->fresh()->commission_amount);
        $this->assertDatabaseCount('partner_commission_entries', 2);

        $this->postJson('/api/v1/admin/refunds/'.$refund['id'].'/dispatch')
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');
        app(PartnerCommissionEventService::class)->processDue();

        self::assertSame(1, PartnerCommissionEvent::query()
            ->where('event_type', PartnerCommissionEvent::EVENT_REFUND_SUCCEEDED)
            ->where('source_id', $refund['id'])
            ->count());
        self::assertSame(1, PartnerCommissionEntry::query()
            ->where('source_type', 'refund_attempt')
            ->where('source_id', $refund['id'])
            ->count());
        $this->assertDatabaseCount('partner_commission_entries', 2);
        self::assertSame(1, RefundAttempt::query()->whereKey($refund['id'])->count());
    }

    private function attributeOrder(string $orderId, string $suffix): GrowthPartner
    {
        $partnerUser = User::factory()->create();
        $partner = GrowthPartner::query()->create([
            'user_id' => $partnerUser->id,
            'code' => 'PS11-'.strtoupper($suffix),
            'channel' => 'field_sales',
            'status' => GrowthPartner::STATUS_ACTIVE,
            'display_name' => 'PS11 '.$suffix,
            'terms_version' => 'ps11-test-v1',
            'terms_accepted_at' => now()->subDay(),
            'activated_at' => now()->subDay(),
        ]);

        PartnerAttribution::query()->create([
            'partner_id' => $partner->id,
            'subject_type' => 'order',
            'subject_id' => $orderId,
            'source' => 'test',
            'attributed_at' => now()->subMinute(),
        ]);

        return $partner;
    }

    private function publishPolicy(string $suffix): PartnerCommissionPolicy
    {
        return PartnerCommissionPolicy::query()->create([
            'version' => 'ps11-test-'.$suffix,
            'status' => PartnerCommissionPolicy::STATUS_PUBLISHED,
            'basis' => PartnerCommissionPolicy::BASIS_PLATFORM_REVENUE,
            'basis_points' => 1_000,
            'rounding_mode' => 'half_up',
            'effective_from' => now()->subDay(),
            'checksum' => hash('sha256', 'ps11-test-'.$suffix),
        ]);
    }

    /** @return array{User, Address, ProductVariant} */
    private function fixture(string $suffix): array
    {
        $customer = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'title' => 'خانه',
            'recipient_name' => 'سجاد',
            'recipient_mobile' => '09123456789',
            'province' => 'البرز',
            'city' => 'کرج',
            'address_line' => 'نشانی تست کمیسیون همکار رشد',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);
        $roastery = Roastery::query()->create([
            'name' => 'روستری Growth '.$suffix,
            'slug' => 'growth-roastery-'.$suffix,
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
            'name' => 'اتیوپی Growth '.$suffix,
            'slug' => 'growth-origin-'.$suffix,
            'country_code' => 'ETH',
        ]);
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'محصول Growth '.$suffix,
            'slug' => 'growth-product-'.$suffix,
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
            'sku' => 'GROWTH-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
        ]);
        RoastBatch::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'GROWTH-BATCH-'.strtoupper($suffix),
            'roasted_at' => now()->subDay(),
            'available_from' => now()->subHours(12),
            'is_active' => true,
        ]);

        return [$customer, $address, $variant];
    }

    /** @return array<string, mixed> */
    private function createOrder(Address $address, ProductVariant $variant, string $suffix): array
    {
        $quote = $this->postJson('/api/v1/checkout/quote', [
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'address_id' => $address->id,
        ])->assertOk()->json('data');

        return $this->postJson('/api/v1/orders', [
            'quote_id' => $quote['id'],
            'idempotency_key' => 'growth:test:order:'.$suffix.':000001',
        ])->assertCreated()->json('data');
    }

    /** @return array<string, mixed> */
    private function payOrder(string $orderId, string $suffix): array
    {
        $payment = $this->postJson('/api/v1/payments/request', [
            'order_id' => $orderId,
            'idempotency_key' => 'growth:test:payment:'.$suffix.':0001',
        ])->assertCreated()->json('data');
        $this->get('/api/v1/payments/testing/callback/'.$payment['payment_id'].'?status=OK')->assertRedirect();
        $this->postJson('/api/v1/payments/'.$payment['payment_id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $order = Order::query()->findOrFail($orderId);
        self::assertNotNull($order->paid_at);

        return $payment;
    }
}
