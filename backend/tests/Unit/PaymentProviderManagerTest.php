<?php

namespace Tests\Unit;

use App\Exceptions\ApiDomainException;
use App\Services\Payments\PaymentProviderManager;
use Tests\TestCase;

final class PaymentProviderManagerTest extends TestCase
{
    public function test_testing_provider_is_forbidden_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        try {
            app(PaymentProviderManager::class)->for('testing');
            $this->fail('The testing payment provider must not resolve in production.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('payment.testing_provider_forbidden', $exception->errorCode);
            $this->assertSame(503, $exception->status);
        }
    }
}
