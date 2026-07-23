<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\NotificationOutbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class AdminOperationsPrivacyTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_non_administrator_cannot_read_operations_data(): void
    {
        $customer = User::factory()->create();
        $this->authenticateWithRole($customer, Role::Customer);

        $this->getJson('/api/v1/admin/operations/audits')->assertForbidden();
        $this->getJson('/api/v1/admin/operations/notifications')->assertForbidden();
    }

    public function test_administrator_receives_redacted_audit_and_masked_notification_data(): void
    {
        $administrator = User::factory()->create(['name' => 'مدیر رستا']);
        $this->authenticateWithRole($administrator, Role::Administrator);

        AuditLog::query()->create([
            'actor_id' => $administrator->id,
            'action' => 'security.test',
            'auditable_type' => User::class,
            'auditable_id' => $administrator->id,
            'request_id' => 'request-test',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'metadata' => [
                'safe' => 'visible',
                'access_token' => 'must-not-leak',
                'nested' => ['email' => 'private@example.com'],
            ],
        ]);

        NotificationOutbox::query()->create([
            'user_id' => $administrator->id,
            'channel' => 'sms',
            'destination' => '09123456789',
            'template_key' => 'order.paid',
            'payload' => ['otp' => '123456', 'order_number' => 'ROSTA-1'],
            'status' => NotificationStatus::Failed->value,
            'provider' => 'testing',
            'deduplication_key' => 'admin-privacy-test',
            'attempts' => 2,
            'last_error' => 'provider timeout',
            'available_at' => now(),
            'failed_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/operations/audits')
            ->assertOk()
            ->assertJsonPath('data.items.0.metadata.safe', 'visible')
            ->assertJsonPath('data.items.0.metadata.access_token', '[redacted]')
            ->assertJsonPath('data.items.0.metadata.nested.email', '[redacted]')
            ->assertJsonMissing(['access_token' => 'must-not-leak']);

        $response = $this->getJson('/api/v1/admin/operations/notifications?status=failed')
            ->assertOk()
            ->assertJsonPath('data.items.0.destination_hint', '091*****789')
            ->assertJsonPath('data.items.0.status', 'failed')
            ->assertJsonMissing(['destination' => '09123456789'])
            ->assertJsonMissing(['otp' => '123456']);

        $this->assertArrayNotHasKey('payload', $response->json('data.items.0'));
    }
}
