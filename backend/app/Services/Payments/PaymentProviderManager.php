<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Exceptions\ApiDomainException;
use App\Services\Payments\Providers\DisabledPaymentProvider;
use App\Services\Payments\Providers\TestingPaymentProvider;
use App\Services\Payments\Providers\ZarinpalPaymentProvider;

final class PaymentProviderManager
{
    public function current(): PaymentProvider
    {
        return $this->for((string) config('rosta.payment.provider', 'disabled'));
    }

    public function for(string $provider): PaymentProvider
    {
        return match (strtolower(trim($provider))) {
            'disabled', 'null' => app(DisabledPaymentProvider::class),
            'testing' => app(TestingPaymentProvider::class),
            'zarinpal' => app(ZarinpalPaymentProvider::class),
            default => throw new ApiDomainException(
                'payment.provider_unknown',
                'ارائه‌دهنده پرداخت شناخته‌شده نیست.',
                503,
            ),
        };
    }

    public function ready(): bool
    {
        if (! config('rosta.payment.enabled', false)) {
            return false;
        }

        $provider = strtolower(trim((string) config('rosta.payment.provider', 'disabled')));

        return match ($provider) {
            'testing' => ! app()->environment('production'),
            'zarinpal' => trim((string) config('rosta.payment.zarinpal.merchant_id')) !== '',
            default => false,
        };
    }
}
