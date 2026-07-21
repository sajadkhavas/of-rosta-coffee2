<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_metadata_is_encrypted_at_rest_and_decrypted_by_the_model(): void
    {
        $user = User::factory()->create();
        $audit = AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => 'identity.profile.updated',
            'request_id' => 'request-audit-123',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'metadata' => ['field' => 'name'],
        ]);

        $rawMetadata = AuditLog::query()
            ->getQuery()
            ->where('id', $audit->id)
            ->value('metadata');

        $this->assertIsString($rawMetadata);
        $this->assertStringNotContainsString('"field":"name"', $rawMetadata);
        $this->assertSame(['field' => 'name'], $audit->fresh()?->metadata);
    }

    public function test_audit_records_cannot_be_updated_or_deleted_through_eloquent(): void
    {
        $audit = AuditLog::query()->create([
            'action' => 'system.health.checked',
            'request_id' => 'request-audit-456',
            'metadata' => ['status' => 'ok'],
        ]);

        try {
            $audit->update(['action' => 'tampered']);
            $this->fail('Updating an audit record should throw.');
        } catch (LogicException) {
            $this->assertSame('system.health.checked', $audit->fresh()?->action);
        }

        $this->expectException(LogicException::class);
        $audit->delete();
    }
}
