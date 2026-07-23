<?php

namespace App\Services\Refunds\Providers;

use App\Contracts\Refunds\RefundProvider;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundAttempt;
use App\Services\Refunds\Data\RefundExecutionResult;

final class TestingRefundProvider implements RefundProvider
{
    public function name(): string
    {
        return 'testing';
    }

    public function execute(
        RefundAttempt $refund,
        PaymentAttempt $payment,
        Order $order,
    ): RefundExecutionResult {
        return new RefundExecutionResult(
            state: 'succeeded',
            referenceId: 'TEST-REFUND-'.$refund->id,
            providerCode: 'testing_refund_succeeded',
            message: 'بازپرداخت آزمایشی با موفقیت ثبت شد.',
            payload: [
                'payment_reference_id' => $payment->reference_id,
                'order_id' => $order->id,
                'amount' => $refund->amount,
            ],
        );
    }
}
