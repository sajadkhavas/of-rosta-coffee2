<?php

namespace App\Services\Refunds\Providers;

use App\Contracts\Refunds\RefundProvider;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundAttempt;
use App\Services\Refunds\Data\RefundExecutionResult;

final class ManualRefundProvider implements RefundProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function execute(
        RefundAttempt $refund,
        PaymentAttempt $payment,
        Order $order,
    ): RefundExecutionResult {
        return new RefundExecutionResult(
            state: 'pending',
            providerCode: 'manual_action_required',
            message: 'بازپرداخت باید در پنل رسمی ارائه‌دهنده انجام و سپس نتیجه ثبت شود.',
            payload: [
                'payment_reference_id' => $payment->reference_id,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
            ],
        );
    }
}
