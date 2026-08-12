<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use RuntimeException;
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
        Redis::shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('Simulated Redis outage.'));

        $response = $this->getJson('/api/v1/health/ready');

        $response
            ->assertStatus(503)
            ->assertJsonPath('data.status', 'not_ready')
            ->assertJsonPath('data.checks.database', true)
            ->assertJsonPath('data.checks.redis', false)
            ->assertJsonPath('data.checks.otp_delivery', true)
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'checks' => ['database', 'redis', 'otp_delivery'],
                    'timestamp',
                ],
            ]);
    }

    public function test_production_readiness_fails_closed_when_otp_is_disabled(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config([
            'rosta.otp.enabled' => false,
            'rosta.otp.driver' => 'disabled',
        ]);

        $response = $this->getJson('/api/v1/health/ready');

        $response
            ->assertStatus(503)
            ->assertJsonPath('data.status', 'not_ready')
            ->assertJsonPath('data.checks.otp_delivery', false);
    }

    public function test_production_readiness_fails_closed_when_kavenegar_config_is_incomplete(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config([
            'rosta.otp.enabled' => true,
            'rosta.otp.driver' => 'kavenegar',
            'rosta.kavenegar.api_key' => '',
            'rosta.otp.kavenegar.templates' => [
                'login' => 'rosta-login',
                'register' => 'rosta-register',
                'verify_mobile' => 'rosta-mobile',
            ],
        ]);

        $this->getJson('/api/v1/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('data.checks.otp_delivery', false);
    }
}
