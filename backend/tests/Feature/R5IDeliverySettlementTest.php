<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\SettlementAllocationStatus;
use App\Models\CheckoutQuote;
use App\Models\Order;
use App\Models\Roastery;
use App\Models\SettlementAllocation;
use App\Models\Shipment;
use App\Models\ShipmentLeg;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\Settlement\SettlementReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class R5IDeliverySettlementTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'rosta.notifications.enabled' => false,
            'rosta.notifications.sms_provider' => 'disabled',
            'rosta.settlement.dispute_window_hours' => 72,
            'rosta.settlement.carrier_webhook_secret' => 'r5i-carrier-secret',
            'rosta.settlement.require_payout_dual_control' => true,
        ]);
    }

    public function test_final_delivery_releases_only_roastery_money_and_pays_one_idempotent_batch(): void
    {
        [$customer, $administrator, $roastery, $order, $subOrder, $finalLeg] = $this->directFixture('direct');
        $payoutConfirmer = User::factory()->create();

        $this->authenticateWithRole($customer, Role::Customer);
        $this->postJson(
            "/api/v1/orders/{$order->id}/shipment-legs/{$finalLeg->id}/delivery-confirmations",
            [
                'idempotency_key' => 'r5i-customer-delivery-direct',
                'proof_type' => 'customer_acknowledgement',
                'customer_note' => 'بسته سالم دریافت شد.',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.sub_orders.0.status', 'delivered')
            ->assertJsonPath('data.sub_orders.0.delivery.settlement_state', 'dispute_hold')
            ->assertJsonPath('data.sub_orders.0.shipment_legs.0.delivery_confirmation.source', 'customer');

        $this->postJson(
            "/api/v1/orders/{$order->id}/shipment-legs/{$finalLeg->id}/delivery-confirmations",
            [
                'idempotency_key' => 'r5i-customer-delivery-direct',
                'proof_type' => 'customer_acknowledgement',
            ],
        )->assertOk();
        $this->assertDatabaseCount('shipment_delivery_confirmations', 1);

        $subOrder->refresh()->forceFill(['dispute_window_ends_at' => now()->subMinute()])->save();
        $release = app(SettlementReleaseService::class);
        $this->assertSame(1, $release->releaseEligible());
        $this->assertSame(0, $release->releaseEligible());
        $this->assertDatabaseHas('settlement_allocations', [
            'sub_order_id' => $subOrder->id,
            'owner_type' => 'roastery',
            'owner_id' => $roastery->id,
            'status' => 'eligible',
        ]);
        $this->assertDatabaseHas('settlement_allocations', [
            'sub_order_id' => $subOrder->id,
            'owner_type' => 'rosta',
            'owner_id' => null,
            'status' => 'held',
        ]);

        $this->authenticateWithRole($administrator, Role::Administrator);
        $batchResponse = $this->postJson('/api/v1/admin/finance/settlement-batches', [
            'roastery_id' => $roastery->id,
            'currency' => 'IRR',
            'idempotency_key' => 'r5i-direct-batch-idempotency',
        ])
            ->assertCreated()
            ->assertJsonPath('data.allocation_count', 1)
            ->assertJsonPath('data.net_total', 4_000_000)
            ->assertJsonPath('data.status', 'pending');
        $batchId = (string) $batchResponse->json('data.id');

        $this->postJson('/api/v1/admin/finance/settlement-batches', [
            'roastery_id' => $roastery->id,
            'currency' => 'IRR',
            'idempotency_key' => 'r5i-direct-batch-idempotency',
        ])->assertCreated()->assertJsonPath('data.id', $batchId);
        $this->assertDatabaseCount('settlement_batches', 1);
        $this->assertDatabaseCount('settlement_batch_allocations', 1);

        $this->postJson("/api/v1/admin/finance/settlement-batches/{$batchId}/resolve", [
            'action' => 'process',
        ])->assertOk()->assertJsonPath('data.status', 'processing');

        $this->postJson("/api/v1/admin/finance/settlement-batches/{$batchId}/resolve", [
            'action' => 'paid',
            'payout_reference' => 'BANK-R5I-SAME-ACTOR',
            'payout_method' => 'manual_bank_transfer',
            'payout_evidence' => [
                'source' => 'bank_receipt',
                'executed_at' => now()->toIso8601String(),
                'amount' => 4_000_000,
                'currency' => 'IRR',
            ],
        ])->assertStatus(409)->assertJsonPath('error.code', 'settlement.payout_dual_control_required');

        $this->authenticateWithRole($payoutConfirmer, Role::Administrator);
        $paidPayload = [
            'action' => 'paid',
            'payout_reference' => 'BANK-R5I-0001',
            'payout_method' => 'manual_bank_transfer',
            'payout_evidence' => [
                'source' => 'bank_receipt',
                'executed_at' => '2026-08-28T12:00:00+00:00',
                'amount' => 4_000_000,
                'currency' => 'IRR',
                'note' => 'PS4.2 acceptance evidence',
            ],
        ];
        $this->postJson("/api/v1/admin/finance/settlement-batches/{$batchId}/resolve", $paidPayload)
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payout_reference', 'BANK-R5I-0001');
        $this->postJson("/api/v1/admin/finance/settlement-batches/{$batchId}/resolve", $paidPayload)
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('settlement_batches', [
            'id' => $batchId,
            'processed_by_id' => $administrator->id,
            'confirmed_by_id' => $payoutConfirmer->id,
            'payout_method' => 'manual_bank_transfer',
        ]);
        $this->assertNotNull(\App\Models\SettlementBatch::query()->findOrFail($batchId)->payout_evidence_hash);
        $this->assertDatabaseHas('settlement_allocations', [
            'sub_order_id' => $subOrder->id,
            'owner_type' => 'roastery',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('settlement_allocations', [
            'sub_order_id' => $subOrder->id,
            'owner_type' => 'rosta',
            'status' => 'held',
        ]);
    }

    public function test_hub_first_leg_delivery_never_completes_customer_delivery(): void
    {
        [$customer, $administrator, , $order, $subOrder] = $this->baseFixture('hub');
        $firstLeg = $this->leg($order, $subOrder, 1, 'roastery_to_rosta_hub', 'picked_up');
        $finalLeg = $this->leg($order, $subOrder, 2, 'rosta_hub_to_customer', 'planned');

        $this->authenticateWithRole($administrator, Role::Administrator);
        $this->postJson("/api/v1/admin/shipment-legs/{$firstLeg->id}/delivery-confirmations", [
            'idempotency_key' => 'r5i-hub-inbound-confirmation',
            'proof_type' => 'administrator_override',
            'proof_payload' => ['reference' => 'HUB-IN-1'],
        ])
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.status', 'shipped')
            ->assertJsonPath('data.sub_orders.0.shipment_legs.0.status', 'delivered')
            ->assertJsonPath('data.sub_orders.0.shipment_legs.1.status', 'awaiting_pickup')
            ->assertJsonPath('data.sub_orders.0.delivery.confirmed_at', null);

        $subOrder->refresh();
        $this->assertSame('shipped', $subOrder->status->value);
        $this->assertNull($subOrder->delivery_confirmed_at);

        $finalLeg->refresh()->forceFill([
            'status' => 'in_transit',
            'carrier' => 'پست',
            'tracking_code' => 'HUB-OUT-1',
            'picked_up_at' => now(),
        ])->save();
        $this->authenticateWithRole($customer, Role::Customer);
        $this->postJson(
            "/api/v1/orders/{$order->id}/shipment-legs/{$finalLeg->id}/delivery-confirmations",
            [
                'idempotency_key' => 'r5i-hub-final-confirmation',
                'proof_type' => 'customer_acknowledgement',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.status', 'delivered')
            ->assertJsonPath('data.sub_orders.0.delivery.settlement_state', 'dispute_hold');
    }

    /** @return array{User, User, Roastery, Order, SubOrder, ShipmentLeg} */
    private function directFixture(string $suffix): array
    {
        [$customer, $administrator, $roastery, $order, $subOrder] = $this->baseFixture($suffix);
        $leg = $this->leg($order, $subOrder, 1, 'roastery_to_customer', 'picked_up');
        Shipment::query()->create([
            'sub_order_id' => $subOrder->id,
            'carrier' => 'پست',
            'tracking_code' => 'R5I-DIRECT-'.$suffix,
            'status' => 'shipped',
            'shipped_at' => now()->subHour(),
        ]);
        return [$customer, $administrator, $roastery, $order, $subOrder, $leg];
    }

    /** @return array{User, User, Roastery, Order, SubOrder} */
    private function baseFixture(string $suffix): array
    {
        $customer = User::factory()->create();
        $administrator = User::factory()->create();
        $roastery = Roastery::query()->create([
            'name' => 'روستری R5I '.$suffix,
            'slug' => 'r5i-roastery-'.$suffix,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $quote = CheckoutQuote::query()->create([
            'user_id' => $customer->id,
            'purpose' => 'checkout',
            'payload_hash' => hash('sha256', 'r5i-'.$suffix),
            'subtotal' => 4_000_000,
            'shipping_total' => 300_000,
            'discount_total' => 0,
            'grand_total' => 4_300_000,
            'currency' => 'IRR',
            'address_snapshot' => [
                'recipient_name' => 'مشتری R5I', 'recipient_mobile' => '09120000000', 'province' => 'تهران',
                'city' => 'تهران', 'address_line' => 'نشانی تست', 'postal_code' => '1234567890',
            ],
            'shipping_snapshot' => [],
            'warnings' => [],
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => now(),
        ]);
        $order = Order::query()->create([
            'user_id' => $customer->id, 'roastery_id' => $roastery->id, 'quote_id' => $quote->id,
            'order_number' => 'R-R5I-'.strtoupper($suffix), 'status' => OrderStatus::Shipped,
            'address_snapshot' => $quote->address_snapshot, 'subtotal' => 4_000_000, 'shipping_total' => 300_000,
            'discount_total' => 0, 'grand_total' => 4_300_000, 'currency' => 'IRR',
            'placed_at' => now()->subDays(2), 'paid_at' => now()->subDays(2),
        ]);
        $subOrder = SubOrder::query()->create([
            'order_id' => $order->id, 'roastery_id' => $roastery->id, 'status' => 'shipped', 'acceptance_status' => 'accepted',
            'subtotal' => 4_000_000, 'shipping_total' => 300_000, 'packaging_total' => 0, 'grinding_total' => 0,
            'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 4_300_000, 'commission_total' => 0,
            'payable_total' => 4_300_000, 'currency' => 'IRR', 'accepted_at' => now()->subDays(2),
            'fulfillment_committed_at' => now()->subDays(2), 'preparation_due_at' => now()->subDay(),
            'handoff_due_at' => now()->subHours(12), 'shipped_at' => now()->subHour(), 'sla_status' => 'handed_off',
        ]);
        $this->allocation($order, $subOrder, 'roastery', $roastery->id, 4_000_000, $suffix.'-product');
        $this->allocation($order, $subOrder, 'rosta', null, 300_000, $suffix.'-platform-shipping');
        return [$customer, $administrator, $roastery, $order, $subOrder];
    }

    private function leg(Order $order, SubOrder $subOrder, int $sequence, string $routeType, string $status): ShipmentLeg
    {
        return ShipmentLeg::query()->create([
            'order_id' => $order->id, 'sub_order_id' => $subOrder->id, 'route_type' => $routeType,
            'sequence' => $sequence, 'status' => $status, 'carrier' => $sequence === 1 ? 'پست' : null,
            'tracking_code' => $sequence === 1 ? 'R5I-TRACK-'.$order->id.'-'.$sequence : null,
            'charge_owner_type' => $routeType === 'rosta_hub_to_customer' ? 'rosta' : 'roastery',
            'charge_owner_id' => $routeType === 'rosta_hub_to_customer' ? null : $subOrder->roastery_id,
            'gross_amount' => 300_000, 'tax_amount' => 0, 'total_amount' => 300_000, 'currency' => 'IRR',
            'origin_snapshot' => ['city' => 'تهران'], 'destination_snapshot' => ['city' => 'تهران'],
            'planned_at' => now()->subHours(2), 'picked_up_at' => $status === 'picked_up' ? now()->subHour() : null,
        ]);
    }

    private function allocation(Order $order, SubOrder $subOrder, string $ownerType, ?string $ownerId, int $amount, string $suffix): void
    {
        SettlementAllocation::query()->create([
            'order_id' => $order->id, 'sub_order_id' => $subOrder->id,
            'allocation_type' => $ownerType === 'roastery' ? 'product' : 'shipping',
            'owner_type' => $ownerType, 'owner_id' => $ownerId, 'status' => SettlementAllocationStatus::Held,
            'gross_amount' => $amount, 'discount_amount' => 0, 'tax_amount' => 0, 'net_amount' => $amount,
            'currency' => 'IRR', 'pricing_version' => 'r5i-test-v1', 'source_reference' => 'r5i-test-'.$suffix,
            'idempotency_key' => 'r5i-test-allocation-'.$suffix, 'metadata' => [],
        ]);
    }
}
