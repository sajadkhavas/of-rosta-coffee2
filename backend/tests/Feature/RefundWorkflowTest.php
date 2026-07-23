<?php

namespace Tests\Feature;

use App\Enums\PaymentAttemptStatus;
use App\Enums\Role;
use App\Models\Address;
use App\Models\FinancialReconciliationCase;
use App\Models\Origin;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundAttempt;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\ShippingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class RefundWorkflowTest extends TestCase
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

    public function test_full_refund_is_idempotent_and_settles_order_once(): void
    {
        [$order, $payment] = $this->paidOrder('full');
        $requester = User::factory()->create();
        $approver = User::factory()->create();

        $this->authenticateWithRole($requester, Role::Administrator);
        $payload = [
            'amount' => $order['grand_total'],
            'reason' => 'رد سفارش توسط روستری',
            'idempotency_key' => 'refund:test:full:00000001',
        ];
        $first = $this->postJson('/api/v1/admin/orders/'.$order['id'].'/refunds', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->json('data');
        $second = $this->postJson('/api/v1/admin/orders/'.$order['id'].'/refunds', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $first['id'])
            ->json('data');
        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('refund_attempts', 1);

        $this->authenticateWithRole($approver, Role::Administrator);
        $this->postJson('/api/v1/admin/refunds/'.$first['id'].'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $this->postJson('/api/v1/admin/refunds/'.$first['id'].'/dispatch')
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.amount', $order['grand_total']);
        $this->postJson('/api/v1/admin/refunds/'.$first['id'].'/dispatch')
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertDatabaseHas('refund_attempts', [
            'id' => $first['id'],
            'status' => 'succeeded',
            'amount' => $order['grand_total'],
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'refunded']);
        $this->assertDatabaseHas('sub_orders', ['order_id' => $order['id'], 'status' => 'refunded']);
        $this->assertDatabaseHas('payment_attempts', ['id' => $payment['payment_id'], 'status' => 'refunded']);
        $this->assertSame(1, RefundAttempt::query()->where('order_id', $order['id'])->count());
    }

    public function test_partial_refund_keeps_balance_open_and_prevents_over_refund(): void
    {
        [$order] = $this->paidOrder('partial');
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $partial = intdiv((int) $order['grand_total'], 2);

        $this->authenticateWithRole($requester, Role::Administrator);
        $refund = $this->postJson('/api/v1/admin/orders/'.$order['id'].'/refunds', [
            'amount' => $partial,
            'reason' => 'بازپرداخت بخشی از سفارش',
            'idempotency_key' => 'refund:test:partial:000001',
        ])->assertCreated()->json('data');

        $this->authenticateWithRole($approver, Role::Administrator);
        $this->postJson('/api/v1/admin/refunds/'.$refund['id'].'/approve')->assertOk();
        $this->postJson('/api/v1/admin/refunds/'.$refund['id'].'/dispatch')
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'refund_pending']);
        $this->assertDatabaseHas('financial_reconciliation_cases', [
            'order_id' => $order['id'],
            'kind' => 'partial_refund_balance_open',
            'status' => 'open',
        ]);

        $this->postJson('/api/v1/admin/orders/'.$order['id'].'/refunds', [
            'amount' => $order['grand_total'],
            'reason' => 'مبلغ بیش از مانده',
            'idempotency_key' => 'refund:test:over:000000001',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'refund.amount_exceeds_available');
    }

    public function test_payment_requires_review_opens_one_reconciliation_case(): void
    {
        [, $payment] = $this->paidOrder('review');
        $attempt = PaymentAttempt::query()->findOrFail($payment['payment_id']);
        $attempt->forceFill([
            'status' => PaymentAttemptStatus::RequiresReview,
            'failure_code' => 'manual_test_review',
            'failure_message' => 'نیازمند تطبیق دستی',
            'review_required_at' => now(),
        ])->save();
        $attempt->forceFill(['failure_message' => 'توضیح تکمیلی'])->save();

        $this->assertSame(1, FinancialReconciliationCase::query()
            ->where('payment_attempt_id', $attempt->id)
            ->where('kind', 'manual_test_review')
            ->count());
    }

    /** @return array{array<string, mixed>, array<string, mixed>} */
    private function paidOrder(string $suffix): array
    {
        [$customer, $address, $variant] = $this->fixture($suffix);
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
            'idempotency_key' => 'refund:test:order:'.$suffix.':000001',
        ])->assertCreated()->json('data');
        $payment = $this->postJson('/api/v1/payments/request', [
            'order_id' => $order['id'],
            'idempotency_key' => 'refund:test:payment:'.$suffix.':0001',
        ])->assertCreated()->json('data');
        $this->get('/api/v1/payments/testing/callback/'.$payment['payment_id'].'?status=OK')
            ->assertRedirect();
        $this->postJson('/api/v1/payments/'.$payment['payment_id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $order['grand_total'] = (int) PaymentAttempt::query()
            ->findOrFail($payment['payment_id'])
            ->amount;

        return [$order, $payment];
    }

    /** @return array{User, Address, ProductVariant} */
    private function fixture(string $suffix): array
    {
        $user = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'title' => 'خانه',
            'recipient_name' => 'سجاد',
            'recipient_mobile' => '09123456789',
            'province' => 'البرز',
            'city' => 'کرج',
            'address_line' => 'نشانی تست بازپرداخت',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);
        $roastery = Roastery::query()->create([
            'name' => 'روستری بازپرداخت '.$suffix,
            'slug' => 'refund-roastery-'.$suffix,
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
            'name' => 'اتیوپی بازپرداخت '.$suffix,
            'slug' => 'refund-origin-'.$suffix,
            'country_code' => 'ETH',
        ]);
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'محصول بازپرداخت '.$suffix,
            'slug' => 'refund-product-'.$suffix,
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
            'sku' => 'REFUND-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
        ]);
        RoastBatch::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'REFUND-BATCH-'.strtoupper($suffix),
            'roasted_at' => now()->subDay(),
            'available_from' => now()->subHours(12),
            'is_active' => true,
        ]);

        return [$user, $address, $variant];
    }
}
