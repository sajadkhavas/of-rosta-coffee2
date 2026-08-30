<?php

namespace Tests\Feature;

use App\Services\Observability\OperationalEventLogger;
use App\Services\Observability\QueueRuntimeHealth;
use App\Support\OperationalContextRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PS6BBackendObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_context_redaction_is_recursive_and_covers_embedded_identifiers(): void
    {
        $redactor = app(OperationalContextRedactor::class);
        $safe = $redactor->redact([
            'token' => 'top-secret-token',
            'nested' => [
                'email' => 'person@example.test',
                'message' => 'Authorization: Bearer abc.def.ghi for person@example.test and 09121234567',
            ],
            'status' => 'failed',
        ]);

        $encoded = json_encode($safe, JSON_THROW_ON_ERROR);
        $this->assertSame('[redacted]', $safe['token']);
        $this->assertSame('[redacted]', $safe['nested']['email']);
        $this->assertSame('failed', $safe['status']);
        $this->assertStringNotContainsString('abc.def.ghi', $encoded);
        $this->assertStringNotContainsString('person@example.test', $encoded);
        $this->assertStringNotContainsString('09121234567', $encoded);
    }

    public function test_operational_event_envelope_is_structured_and_redacted_before_logging(): void
    {
        $envelope = app(OperationalEventLogger::class)->envelope('queue.failed', [
            'queue' => 'notifications',
            'password' => 'never-log-me',
            'exception_class' => 'RuntimeException',
        ]);

        $this->assertSame('queue.failed', $envelope['event']);
        $this->assertSame('rosta-api', $envelope['service']);
        $this->assertSame('notifications', $envelope['context']['queue']);
        $this->assertSame('[redacted]', $envelope['context']['password']);
        $this->assertSame('RuntimeException', $envelope['context']['exception_class']);
        $this->assertStringNotContainsString('never-log-me', json_encode($envelope, JSON_THROW_ON_ERROR));
    }

    public function test_queue_health_reports_dead_letter_metadata_without_payload_or_exception_content(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('observability.max_failed_jobs', 0);

        DB::table('failed_jobs')->insert([
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'connection' => 'redis',
            'queue' => 'notifications',
            'payload' => '{"token":"must-not-leak"}',
            'exception' => 'secret exception body',
            'failed_at' => now()->subMinutes(5),
        ]);

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $snapshot = app(QueueRuntimeHealth::class)->snapshot();
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);

        $this->assertTrue($snapshot['ready']);
        $this->assertTrue($snapshot['backend_available']);
        $this->assertSame(1, $snapshot['failed_jobs']);
        $this->assertTrue($snapshot['degraded']);
        $this->assertNotNull($snapshot['oldest_failed_age_seconds']);
        $this->assertStringNotContainsString('must-not-leak', $encoded);
        $this->assertStringNotContainsString('secret exception body', $encoded);
        $this->assertLessThanOrEqual(6, $queries, 'Queue health must stay within a bounded query budget.');
    }

    public function test_sync_queue_is_fail_closed_when_application_environment_is_production(): void
    {
        config()->set('queue.default', 'sync');
        $previous = $this->app->environment();
        $this->app['env'] = 'production';

        try {
            $snapshot = app(QueueRuntimeHealth::class)->snapshot();
            $this->assertFalse($snapshot['backend_available']);
            $this->assertFalse($snapshot['ready']);
        } finally {
            $this->app['env'] = $previous;
        }
    }
}
