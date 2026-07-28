<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CheckoutQuote;
use App\Models\GrindingProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemService;
use App\Models\Roastery;
use App\Models\RostaHub;
use App\Models\ShipmentLeg;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Hub\RostaHubOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class R5JRostaHubOperationsTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_hub_chain_of_custody_is_idempotent_private_and_blocks_early_handoff(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [$customer, $admin, $operator, $other, $seller, $roastery, $order, $service, $inbound, $outbound] = $this->fixture();
        $workItem = app(RostaHubOperationsService::class)->createForRoute($service, $inbound, $outbound, 250, 2);

        $this->authenticateWithRole($admin, Role::Administrator);
        $this->postJson("/api/v1/admin/hub-operations/work-items/{$workItem->id}/transition", [
            'action' => 'receive', 'idempotency_key' => 'r5j-receive-before-inbound',
        ])->assertConflict()->assertJsonPath('error.code', 'hub_operation.inbound_not_delivered');

        $inbound->forceFill(['status' => 'delivered', 'delivered_at' => now()])->save();
        $this->postJson("/api/v1/admin/hub-operations/work-items/{$workItem->id}/transition", [
            'action' => 'receive', 'idempotency_key' => 'r5j-receive-0001', 'evidence' => ['reference' => 'INBOUND-PRIVATE'],
        ])->assertOk()->assertJsonPath('data.status', 'received')->assertJsonMissing(['private_evidence']);
        $this->postJson("/api/v1/admin/hub-operations/work-items/{$workItem->id}/transition", [
            'action' => 'receive', 'idempotency_key' => 'r5j-receive-0001',
        ])->assertOk()->assertJsonPath('data.status', 'received');

        $this->postJson("/api/v1/admin/hub-operations/work-items/{$workItem->id}/assign", [
            'operator_id' => $operator->id, 'idempotency_key' => 'r5j-assign-0001', 'note' => 'شیفت صبح',
        ])->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.assigned_operator.id', $operator->id);

        Auth::forgetGuards();
        $this->authenticateWithRole($operator, Role::HubOperator, 'rosta_hub', $workItem->hub_id);
        foreach ([
            ['start_grinding', 'r5j-start-0001', 'grinding'],
            ['submit_quality_check', 'r5j-qc-submit-1', 'quality_check'],
            ['quality_fail', 'r5j-qc-fail-1', 'rework_required'],
            ['restart_grinding', 'r5j-rework-1', 'grinding'],
            ['submit_quality_check', 'r5j-qc-submit-2', 'quality_check'],
            ['quality_pass', 'r5j-qc-pass-1', 'packaging'],
            ['mark_ready', 'r5j-ready-0001', 'ready_for_outbound'],
            ['handoff', 'r5j-handoff-1', 'handed_off'],
        ] as [$action, $key, $status]) {
            $this->postJson("/api/v1/hub-operations/work-items/{$workItem->id}/transition", [
                'action' => $action, 'idempotency_key' => $key, 'evidence' => ['note' => 'private-'.$action],
            ])->assertOk()->assertJsonPath('data.status', $status)->assertJsonMissingPath('data.private_evidence');
        }

        Auth::forgetGuards();
        $this->authenticateWithRole($other, Role::HubOperator, 'rosta_hub', $workItem->hub_id);
        $this->postJson("/api/v1/hub-operations/work-items/{$workItem->id}/transition", [
            'action' => 'start_grinding', 'idempotency_key' => 'r5j-wrong-operator',
        ])->assertForbidden()->assertJsonPath('error.code', 'hub_operation.not_assigned');

        $outbound->refresh();
        $service->refresh();
        $this->assertSame('picked_up', $outbound->status->value);
        $this->assertSame('completed', $service->status->value);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'event_type' => 'hub.operation.handoff']);
        $this->assertDatabaseCount('hub_work_item_actions', 11);

        Auth::forgetGuards();
        $this->authenticateWithRole($customer, Role::Customer);
        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.items.0.services.0.hub_operation.status', 'handed_off')
            ->assertJsonMissingPath('data.assigned_operator')
            ->assertJsonMissingPath('data.private_evidence')
            ->assertJsonMissing(['private_evidence' => ['reference' => 'INBOUND-PRIVATE']]);

        Auth::forgetGuards();
        $this->authenticateWithRole($seller, Role::RoasteryManager, 'roastery', $roastery->id);
        $sellerResponse = $this->getJson("/api/v1/seller/roasteries/{$roastery->id}/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.sub_orders.0.shipment_legs.0.route_type', 'roastery_to_rosta_hub')
            ->assertJsonPath('data.sub_orders.0.shipment_legs.0.status', 'delivered')
            ->assertJsonPath('data.sub_orders.0.items.0.services.0.hub_operation.status', 'received')
            ->assertJsonMissingPath('data.sub_orders.0.items.0.services.0.hub_operation.ready_at')
            ->assertJsonMissingPath('data.sub_orders.0.items.0.services.0.hub_operation.handed_off_at')
            ->assertJsonMissing(['route_type' => 'rosta_hub_to_customer'])
            ->assertJsonMissingPath('data.assigned_operator')
            ->assertJsonMissingPath('data.private_evidence')
            ->assertJsonMissing(['private_evidence' => ['reference' => 'INBOUND-PRIVATE']]);

        $this->assertNotNull(
            $sellerResponse->json('data.sub_orders.0.items.0.services.0.hub_operation.received_at'),
        );
        $sellerHubEvents = collect($sellerResponse->json('data.events'))
            ->pluck('type')
            ->filter(static fn (string $type): bool => str_starts_with($type, 'hub.operation.'))
            ->values()
            ->all();
        $this->assertSame(['hub.operation.receive'], $sellerHubEvents);
    }

    private function fixture(): array
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create();
        $operator = User::factory()->create();
        $other = User::factory()->create();
        $seller = User::factory()->create();
        $roastery = Roastery::query()->create(['name' => 'R5J Roastery', 'slug' => 'r5j-roastery', 'description' => '', 'status' => 'verified', 'verified_at' => now()]);
        $hub = RostaHub::query()->create(['code' => 'r5j-hub', 'name' => 'هاب R5J', 'province' => 'تهران', 'city' => 'تهران', 'fee_mode' => 'free', 'fee_amount' => 0, 'preparation_minutes' => 20, 'capacity_per_day' => 100, 'supported_weights' => [250], 'is_active' => true]);
        $profile = GrindingProfile::query()->create(['code' => 'r5j-profile', 'version' => 1, 'public_name' => 'V60', 'brew_method' => 'v60', 'recipe_snapshot' => [], 'is_active' => true]);
        UserRole::query()->create(['user_id' => $operator->id, 'role' => Role::HubOperator->value, 'scope_type' => 'rosta_hub', 'scope_id' => $hub->id]);
        UserRole::query()->create(['user_id' => $other->id, 'role' => Role::HubOperator->value, 'scope_type' => 'rosta_hub', 'scope_id' => $hub->id]);
        $quote = CheckoutQuote::query()->create(['user_id' => $customer->id, 'purpose' => 'checkout', 'payload_hash' => hash('sha256', 'r5j'), 'subtotal' => 2_000_000, 'shipping_total' => 0, 'discount_total' => 0, 'grand_total' => 2_000_000, 'currency' => 'IRR', 'address_snapshot' => ['recipient_name' => 'مشتری', 'recipient_mobile' => '09120000000', 'province' => 'تهران', 'city' => 'تهران', 'address_line' => 'نشانی', 'postal_code' => '1234567890'], 'shipping_snapshot' => [], 'warnings' => [], 'expires_at' => now()->addHour(), 'consumed_at' => now()]);
        $order = Order::query()->create(['user_id' => $customer->id, 'roastery_id' => $roastery->id, 'quote_id' => $quote->id, 'order_number' => 'R-R5J-001', 'status' => 'shipped', 'address_snapshot' => $quote->address_snapshot, 'subtotal' => 2_000_000, 'shipping_total' => 0, 'discount_total' => 0, 'grand_total' => 2_000_000, 'currency' => 'IRR', 'placed_at' => now(), 'paid_at' => now()]);
        $subOrder = SubOrder::query()->create(['order_id' => $order->id, 'roastery_id' => $roastery->id, 'status' => 'shipped', 'acceptance_status' => 'accepted', 'subtotal' => 2_000_000, 'shipping_total' => 0, 'packaging_total' => 0, 'grinding_total' => 0, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 2_000_000, 'commission_total' => 0, 'payable_total' => 2_000_000, 'currency' => 'IRR', 'accepted_at' => now(), 'fulfillment_committed_at' => now(), 'sla_status' => 'on_track', 'shipped_at' => now()]);
        $item = OrderItem::query()->create(['order_id' => $order->id, 'sub_order_id' => $subOrder->id, 'quantity' => 2, 'unit_price' => 1_000_000, 'line_total' => 2_000_000, 'product_snapshot' => ['id' => 'p-r5j', 'name' => 'قهوه تست', 'slug' => 'r5j-coffee', 'primary_image' => null], 'variant_snapshot' => ['id' => 'v-r5j', 'sku' => 'R5J-250', 'weight_grams' => 250, 'currency' => 'IRR'], 'roast_batch_snapshot' => []]);
        $service = OrderItemService::query()->create(['order_id' => $order->id, 'sub_order_id' => $subOrder->id, 'order_item_id' => $item->id, 'service_type' => 'grinding', 'provider_type' => 'rosta_hub', 'provider_hub_id' => $hub->id, 'grinding_profile_id' => $profile->id, 'status' => 'requested', 'service_fee' => 0, 'packaging_fee' => 0, 'shipping_fee' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'currency' => 'IRR', 'pricing_snapshot' => [], 'service_snapshot' => ['label' => 'آسیاب هاب رستا']]);
        $inbound = ShipmentLeg::query()->create(['order_id' => $order->id, 'sub_order_id' => $subOrder->id, 'order_item_service_id' => $service->id, 'route_type' => 'roastery_to_rosta_hub', 'sequence' => 1, 'status' => 'in_transit', 'charge_owner_type' => 'rosta', 'gross_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'currency' => 'IRR', 'origin_snapshot' => [], 'destination_snapshot' => [], 'planned_at' => now(), 'picked_up_at' => now()]);
        $outbound = ShipmentLeg::query()->create(['order_id' => $order->id, 'sub_order_id' => $subOrder->id, 'order_item_service_id' => $service->id, 'route_type' => 'rosta_hub_to_customer', 'sequence' => 2, 'status' => 'planned', 'charge_owner_type' => 'rosta', 'gross_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'currency' => 'IRR', 'origin_snapshot' => [], 'destination_snapshot' => [], 'planned_at' => now()]);

        return [$customer, $admin, $operator, $other, $seller, $roastery, $order, $service, $inbound, $outbound];
    }
}
