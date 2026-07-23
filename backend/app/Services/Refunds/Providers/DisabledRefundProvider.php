<?php

namespace App\Services\Refunds\Providers;

use App\Contracts\Refunds\RefundProvider;
use App\Exceptions\RefundProviderException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundAttempt;
use App\Services\Refunds\Data\RefundExecutionResult;

final class DisabledRefundProvider implements RefundProvider
{
    public function name(): string
    {
        return 'disabled';
    }

    public function execute(
        RefundAttempt $refund,
        PaymentAttempt $payment,
        Order $order,
    ): RefundExecutionResult {
        throw new RefundProviderException(
            'سرویس بازپرداخت فعال نیست.',
            'refund_provider_disabled',
        );
    }
}
