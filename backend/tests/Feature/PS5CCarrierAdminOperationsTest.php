<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ShipmentLeg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class PS5CCarrierAdminOperationsTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rosta.settlement.carrier_webhook_secret' => 'ps5c-carrier-secret',
            'rosta.carrier.webhook_tolerance_seconds' => 300,
        ]);
    }

    public function test_signed_carrier_event_is_timestamp_bounded_and_replay_safe(): void
    {
        $leg = $this->shipmentLeg('picked_up', 'manual-post', 'PS5C-TRACK-1');
        $payload = [
            'carrier' => 'manual-post',
            'tracking_code' => 'PS5C-TRACK-1',
            'event_type' => 'in_transit',
            'occurred_at' => now()->toIso8601String(),
            'evidence_reference' => 'carrier-scan-1',
        ];
        $eventId = 'evt-ps5c-00000001';

        $first = $this->signedCarrierEvent($payload, $eventId, now()->timestamp);
        $first
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit')
            ->assertJsonPath('data.replayed', false);
        $this->assertDatabaseCount('carrier_webhook_receipts', 1);
        $this->assertSame('in_transit', $leg->fresh()?->status->value);

        $this->signedCarrierEvent($payload, $eventId, now()->timestamp)
            ->assertOk()
            ->assertJsonPath('data.replayed', true);
        $this->assertDatabaseCount('carrier_webhook_receipts', 1);

        $changed = $payload;
        $changed['event_type'] = 'requires_review';
        $this->signedCarrierEvent($changed, $eventId, now()->timestamp)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'carrier.webhook_replay_conflict');

        $this->signedCarrierEvent($payload, 'evt-ps5c-expired-01', now()->subMinutes(10)->timestamp)
            ->assertStatus(403);
    }

    public function test_administrator_can_manage_manual_carrier_without_money_mutation(): void
    {
        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);
        $leg = $this->shipmentLeg('awaiting_pickup', null, null);

        $this->patchJson("/api/v1/admin/shipment-legs/{$leg->id}/carrier", [
            'carrier' => 'manual-post',
            'tracking_code' => 'PS5C-MANUAL-1',
            'status' => 'picked_up',
            'reason' => 'ثبت تحویل دستی بسته به حامل پس از کنترل رسید.',
        ])
            ->assertOk()
            ->assertJsonPath('data.carrier', 'manual-post')
            ->assertJsonPath('data.status', 'picked_up');

        $fresh = $leg->fresh();
        $this->assertSame('PS5C-MANUAL-1', $fresh?->tracking_code);
        $this->assertNotNull($fresh?->picked_up_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'carrier.shipment_leg_managed',
            'actor_id' => $administrator->id,
        ]);
    }

    public function test_failed_job_browser_output_is_redacted_and_forget_requires_second_admin(): void
    {
        $firstAdmin = User::factory()->create();
        $secondAdmin = User::factory()->create();
        $retryUuid = '11111111-1111-4111-8111-111111111111';
        $forgetUuid = '22222222-2222-4222-8222-222222222222';
        $this->failedJob($retryUuid, 'App\\Jobs\\SafeRetryJob', 'top-secret-retry');
        $this->failedJob($forgetUuid, 'App\\Jobs\\SafeForgetJob', 'top-secret-forget');

        $this->authenticateWithRole($firstAdmin, Role::Administrator);
        $response = $this->getJson('/api/v1/admin/operations/failed-jobs')
            ->assertOk()
            ->assertJsonPath('data.items.0.uuid', $forgetUuid);
        $this->assertStringNotContainsString('top-secret', $response->getContent());
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', ['id' => [$retryUuid]])
            ->andReturn(0);
        $this->postJson("/api/v1/admin/operations/failed-jobs/{$retryUuid}/retry", [
            'reason' => 'تلاش مجدد پس از رفع علت عملیاتی و بررسی وابستگی سرویس.',
        ])->assertOk()->assertJsonPath('data.status', 'retry_requested');

        $forget = $this->postJson("/api/v1/admin/operations/failed-jobs/{$forgetUuid}/forget-requests", [
            'reason' => 'حذف رکورد پس از ثبت شواهد و تشخیص غیرقابل‌بازیابی بودن کار.',
        ])->assertCreated();
        $operationId = (string) $forget->json('data.id');

        $this->postJson("/api/v1/admin/operations/failed-job-forget-requests/{$operationId}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'operations.failed_job_dual_control_required');

        $this->authenticateWithRole($secondAdmin, Role::Administrator);
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:forget', ['id' => $forgetUuid])
            ->andReturn(0);
        $this->postJson("/api/v1/admin/operations/failed-job-forget-requests/{$operationId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.confirmed_by_id', $secondAdmin->id);
    }

    /** @param array<string, mixed> $payload */
    private function signedCarrierEvent(array $payload, string $eventId, int $timestamp): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac(
            'sha256',
            'v1:'.$timestamp.':'.$eventId.':'.$body,
            'ps5c-carrier-secret',
        );

        return $this->call(
            'POST',
            '/api/v1/webhooks/carriers/events',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_ROSTA_CARRIER_TIMESTAMP' => (string) $timestamp,
                'HTTP_X_ROSTA_CARRIER_EVENT_ID' => $eventId,
                'HTTP_X_ROSTA_CARRIER_SIGNATURE' => 'v1='.$signature,
            ],
            $body,
        );
    }

    private function shipmentLeg(string $status, ?string $carrier, ?string $trackingCode): ShipmentLeg
    {
        return ShipmentLeg::query()->create([
            'route_type' => 'roastery_to_customer',
            'sequence' => 1,
            'status' => $status,
            'carrier' => $carrier,
            'tracking_code' => $trackingCode,
            'charge_owner_type' => 'roastery',
            'charge_owner_id' => null,
            'gross_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'currency' => 'IRR',
            'origin_snapshot' => ['city' => 'تهران'],
            'destination_snapshot' => ['city' => 'کرج'],
            'planned_at' => now()->subHour(),
            'picked_up_at' => $status === 'picked_up' ? now()->subMinutes(30) : null,
        ]);
    }

    private function failedJob(string $uuid, string $displayName, string $secret): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => $displayName,
                'data' => ['command' => $secret],
            ], JSON_THROW_ON_ERROR),
            'exception' => "RuntimeException: {$secret}",
            'failed_at' => now(),
        ]);
    }
}
