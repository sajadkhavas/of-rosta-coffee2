<?php

namespace App\Services\Refunds;

use App\Contracts\Refunds\RefundProvider;
use App\Exceptions\ApiDomainException;
use App\Services\Refunds\Providers\DisabledRefundProvider;
use App\Services\Refunds\Providers\ManualRefundProvider;
use App\Services\Refunds\Providers\TestingRefundProvider;

final class RefundProviderManager
{
    public function current(): RefundProvider
    {
        return $this->for((string) config('rosta.refund.provider', 'disabled'));
    }

    public function for(string $provider): RefundProvider
    {
        return match (strtolower(trim($provider))) {
            'disabled', 'null' => app(DisabledRefundProvider::class),
            'manual' => app(ManualRefundProvider::class),
            'testing' => $this->testingProvider(),
            default => throw new ApiDomainException(
                'refund.provider_unknown',
                'ارائه‌دهنده بازپرداخت شناخته‌شده نیست.',
                503,
            ),
        };
    }

    public function ready(): bool
    {
        if (! config('rosta.refund.enabled', false)) {
            return false;
        }

        return match (strtolower(trim((string) config('rosta.refund.provider', 'disabled')))) {
            'manual' => true,
            'testing' => ! app()->environment('production'),
            default => false,
        };
    }

    private function testingProvider(): RefundProvider
    {
        if (! app()->environment('production')) {
            return app(TestingRefundProvider::class);
        }

        throw new ApiDomainException(
            'refund.testing_provider_forbidden',
            'ارائه‌دهنده آزمایشی بازپرداخت در Production مجاز نیست.',
            503,
        );
    }
}
