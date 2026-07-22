<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\CheckoutQuote;
use App\Models\NotificationOutbox;
use App\Models\Order;
use App\Models\Roastery;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\Notifications\NotificationOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rosta.notifications.enabled' => true,
            'rosta.notifications.sms_provider' => 'testing',
            'rosta.notifications.max_attempts' => 3,
            'rosta.notifications.retry_seconds' => 30,
        ]);
    }

    public function test_paid_transition_queues_once_and_worker_marks_message_sent(): void
    {
        $user = User::factory()->create(['mobile' => '09123456789']);
        $roastery = Roastery::query()->create([
            'name' => 'روستری Outbox',
            'slug' => 'outbox-roastery',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $quote = CheckoutQuote::query()->create([
            'user_id' => $user->id,
            'address_id' => null,
            'roastery_id' => $roastery->id,
            'coupon_id' => null,
            'purpose' => 'checkout',
            'payload_hash' => hash('sha256', 'notification-outbox-test'),
            'subtotal' => 2_000_000,
            'shipping_total' => 300_000,
            'discount_total' => 0,
            'grand_total' => 2_300_000,
            'currency' => 'IRR',
            'address_snapshot' => [
                'recipient_name' => 'سجاد',
                'recipient_mobile' => '09123456789',
                'province' => 'البرز',
                'city' => 'کرج',
                'address_line' => 'نشانی تست',
                'postal_code' => '1234567890',
            ],
            'shipping_snapshot' => [],
            'warnings' => [],
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => now(),
        ]);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'roastery_id' => $roastery->id,
            'quote_id' => $quote->id,
            'order_number' => 'R-OUTBOX-0001',
            'status' => OrderStatus::AwaitingPayment,
            'address_snapshot' => $quote->address_snapshot,
            'subtotal' => 2_000_000,
            'shipping_total' => 300_000,
            'discount_total' => 0,
            'grand_total' => 2_300_000,
            'currency' => 'IRR',
            'placed_at' => now(),
        ]);
        SubOrder::query()->create([
            'order_id' => $order->id,
            'roastery_id' => $roastery->id,
            'status' => 'pending_acceptance',
            'subtotal' => 2_000_000,
            'shipping_total' => 300_000,
        ]);

        $order->forceFill([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ])->save();
        $order->forceFill(['status' => OrderStatus::Paid])->save();

        $this->assertDatabaseCount('notification_outbox', 1);
        $outbox = NotificationOutbox::query()->firstOrFail();
        $this->assertSame('pending', $outbox->status->value);
        $this->assertSame('09123456789', $outbox->destination);
        $this->assertSame('order.paid', $outbox->template_key);

        $sent = app(NotificationOutboxService::class)->dispatchPending(10);

        $this->assertSame(1, $sent);
        $outbox->refresh();
        $this->assertSame('sent', $outbox->status->value);
        $this->assertSame('testing', $outbox->provider);
        $this->assertNotNull($outbox->provider_message_id);
        $this->assertNotNull($outbox->sent_at);
    }
}
