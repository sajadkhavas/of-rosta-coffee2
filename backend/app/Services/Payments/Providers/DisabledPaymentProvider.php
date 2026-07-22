<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentProvider;
use App\Exceptions\ApiDomainException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Data\PaymentInitiationResult;
use App\Services\Payments\Data\PaymentVerificationResult;

final class DisabledPaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'disabled';
    }

    public function initiate(
        PaymentAttempt $attempt,
        Order $order,
    ): PaymentInitiationResult {
        throw $this->unavailable();
    }

    public function verify(
        PaymentAttempt $attempt,
        Order $order,
        ?string $callbackStatus,
    ): PaymentVerificationResult {
        throw $this->unavailable();
    }

    private function unavailable(): ApiDomainException
    {
        return new ApiDomainException(
            'payment.unavailable',
            'درگاه پرداخت فعال نیست.',
            503,
        );
    }
}
