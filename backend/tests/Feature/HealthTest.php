<?php

namespace Tests\Feature;

use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_liveness_returns_the_frozen_contract_envelope(): void
    {
        $response = $this->withHeader('X-Request-ID', 'test-request-123')
            ->getJson('/api/v1/health/live');

        $response
            ->assertOk()
            ->assertHeader('X-Request-ID', 'test-request-123')
            ->assertHeader('X-Rosta-Contract-Version', '2026-07-21-phase-6')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'rosta-api')
            ->assertJsonPath('data.contract_version', '2026-07-21-phase-6');
    }

    public function test_invalid_request_id_is_replaced_with_a_safe_identifier(): void
    {
        $response = $this->withHeader('X-Request-ID', 'bad value')
            ->getJson('/api/v1/health/live');

        $response->assertOk();

        $requestId = (string) $response->headers->get('X-Request-ID');

        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9._:-]{8,128}$/',
            $requestId,
        );
        $this->assertNotSame('bad value', $requestId);
    }

    public function test_readiness_never_claims_ready_when_a_dependency_is_missing(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $response
            ->assertStatus(503)
            ->assertJsonPath('data.status', 'not_ready')
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'checks' => ['database', 'redis'],
                    'timestamp',
                ],
            ]);
    }
}
