<?php

namespace App\Contracts\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Data\PaymentInitiationResult;
use App\Services\Payments\Data\PaymentVerificationResult;

interface PaymentProvider
{
    public function name(): string;

    public function initiate(
        PaymentAttempt $attempt,
        Order $order,
    ): PaymentInitiationResult;

    public function verify(
        PaymentAttempt $attempt,
        Order $order,
        ?string $callbackStatus,
    ): PaymentVerificationResult;
}
