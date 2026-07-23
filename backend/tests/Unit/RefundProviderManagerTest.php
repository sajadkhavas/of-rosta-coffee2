<?php

namespace Tests\Unit;

use App\Exceptions\ApiDomainException;
use App\Services\Refunds\RefundProviderManager;
use Tests\TestCase;

final class RefundProviderManagerTest extends TestCase
{
    public function test_testing_provider_is_forbidden_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        try {
            app(RefundProviderManager::class)->for('testing');
            $this->fail('The testing refund provider must not resolve in production.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('refund.testing_provider_forbidden', $exception->errorCode);
            $this->assertSame(503, $exception->status);
        }
    }
}
