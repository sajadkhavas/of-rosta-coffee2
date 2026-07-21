<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use App\Data\Payments\PaymentCallbackResult;
use App\Data\Payments\PaymentInitiationResult;
use App\Data\Payments\PaymentRefundResult;
use App\Data\Payments\PaymentVerificationResult;
use App\Exceptions\ApiDomainException;
use App\Models\PaymentAttempt;

final class DisabledPaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'disabled';
    }

    public function initiate(PaymentAttempt $attempt): PaymentInitiationResult
    {
        $this->unavailable();
    }

    public function verify(PaymentAttempt $attempt): PaymentVerificationResult
    {
        $this->unavailable();
    }

    public function parseCallback(
        array $headers,
        array $payload,
        string $rawBody,
    ): PaymentCallbackResult {
        $this->unavailable();
    }

    public function refund(
        PaymentAttempt $attempt,
        int $amount,
        string $reason,
    ): PaymentRefundResult {
        $this->unavailable();
    }

    private function unavailable(): never
    {
        throw new ApiDomainException(
            'payment.provider_disabled',
            'درگاه پرداخت هنوز فعال نشده است.',
            503,
        );
    }
}
