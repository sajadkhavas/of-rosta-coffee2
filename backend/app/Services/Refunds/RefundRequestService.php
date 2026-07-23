<?php

namespace App\Services\Refunds;

use App\Enums\PaymentAttemptStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundAttempt;
use App\Models\User;
use Illuminate\Http\Request;

final class RefundRequestService
{
    public function __construct(private readonly RefundService $refunds) {}

    /**
     * @param  array{amount?: int|null, reason: string, idempotency_key: string}  $input
     */
    public function request(
        User $actor,
        Order $order,
        array $input,
        Request $request,
    ): RefundAttempt {
        if (! isset($input['amount'])) {
            $payment = PaymentAttempt::query()
                ->where('order_id', $order->id)
                ->where('status', PaymentAttemptStatus::Verified->value)
                ->latest('verified_at')
                ->first();

            if ($payment instanceof PaymentAttempt) {
                $reservedAmount = (int) RefundAttempt::query()
                    ->where('payment_attempt_id', $payment->id)
                    ->whereIn('status', [
                        RefundStatus::Requested->value,
                        RefundStatus::Approved->value,
                        RefundStatus::Processing->value,
                        RefundStatus::Succeeded->value,
                        RefundStatus::RequiresReview->value,
                    ])
                    ->sum('amount');
                $input['amount'] = max(0, $payment->amount - $reservedAmount);
            }
        }

        return $this->refunds->request($actor, $order, $input, $request);
    }
}
