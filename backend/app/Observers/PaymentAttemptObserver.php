<?php

namespace App\Observers;

use App\Enums\PaymentAttemptStatus;
use App\Models\PaymentAttempt;
use App\Services\Finance\FinancialReconciliationService;

final class PaymentAttemptObserver
{
    public function __construct(
        private readonly FinancialReconciliationService $reconciliation,
    ) {}

    public function updated(PaymentAttempt $attempt): void
    {
        if (
            $attempt->status !== PaymentAttemptStatus::RequiresReview
            || ! $attempt->wasChanged('status')
        ) {
            return;
        }

        $this->reconciliation->openPaymentReview(
            $attempt,
            $attempt->failure_code ?: 'payment_requires_review',
            $attempt->failure_message ?: 'پرداخت برای تطبیق مالی دستی علامت‌گذاری شده است.',
            [
                'provider' => $attempt->provider,
                'reference_id' => $attempt->reference_id,
                'gateway_code' => $attempt->gateway_code,
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
            ],
        );
    }
}
