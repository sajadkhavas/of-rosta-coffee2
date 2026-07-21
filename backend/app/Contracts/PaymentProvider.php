<?php

namespace App\Contracts;

use App\Data\Payments\PaymentCallbackResult;
use App\Data\Payments\PaymentInitiationResult;
use App\Data\Payments\PaymentRefundResult;
use App\Data\Payments\PaymentVerificationResult;
use App\Models\PaymentAttempt;

interface PaymentProvider
{
    public function name(): string;

    public function initiate(PaymentAttempt $attempt): PaymentInitiationResult;

    public function verify(PaymentAttempt $attempt): PaymentVerificationResult;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payload
     */
    public function parseCallback(
        array $headers,
        array $payload,
        string $rawBody,
    ): PaymentCallbackResult;

    public function refund(
        PaymentAttempt $attempt,
        int $amount,
        string $reason,
    ): PaymentRefundResult;
}
