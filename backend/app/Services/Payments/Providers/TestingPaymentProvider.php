<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentProvider;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Data\PaymentInitiationResult;
use App\Services\Payments\Data\PaymentVerificationResult;

final class TestingPaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'testing';
    }

    public function initiate(
        PaymentAttempt $attempt,
        Order $order,
    ): PaymentInitiationResult {
        $authority = 'TEST-'.$attempt->id;

        return new PaymentInitiationResult(
            authority: $authority,
            redirectUrl: route('api.v1.payments.testing.callback', [
                'paymentId' => $attempt->id,
                'status' => 'OK',
            ]),
            gatewayCode: 'TEST_CREATED',
            requestPayload: [
                'amount' => $attempt->amount_provider,
                'order_id' => $order->id,
            ],
            responsePayload: [
                'authority' => $authority,
                'mode' => 'testing',
            ],
        );
    }

    public function verify(
        PaymentAttempt $attempt,
        Order $order,
        ?string $callbackStatus,
    ): PaymentVerificationResult {
        if (strtoupper(trim((string) $callbackStatus)) !== 'OK') {
            return PaymentVerificationResult::failed(
                state: 'cancelled',
                failureCode: 'testing_cancelled',
                message: 'پرداخت آزمایشی لغو شد.',
                gatewayCode: 'TEST_CANCELLED',
                payload: ['status' => $callbackStatus],
            );
        }

        return PaymentVerificationResult::verified(
            referenceId: 'TEST-REF-'.$attempt->id,
            gatewayCode: 'TEST_VERIFIED',
            payload: [
                'mode' => 'testing',
                'order_id' => $order->id,
                'amount' => $attempt->amount_provider,
            ],
        );
    }
}
