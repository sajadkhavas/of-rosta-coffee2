<?php

namespace App\Contracts\Refunds;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundAttempt;
use App\Services\Refunds\Data\RefundExecutionResult;

interface RefundProvider
{
    public function name(): string;

    public function execute(
        RefundAttempt $refund,
        PaymentAttempt $payment,
        Order $order,
    ): RefundExecutionResult;
}
