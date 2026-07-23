<?php

namespace App\Services\Refunds;

use App\Enums\RefundStatus;
use App\Exceptions\ApiDomainException;
use App\Models\RefundAttempt;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class RefundDispatchService
{
    public function __construct(private readonly RefundService $refunds) {}

    public function dispatch(
        User $actor,
        RefundAttempt $refund,
        Request $request,
    ): RefundAttempt {
        try {
            return Cache::lock('refund-dispatch:'.$refund->id, 30)->block(
                5,
                function () use ($actor, $refund, $request): RefundAttempt {
                    $fresh = RefundAttempt::query()->findOrFail($refund->id);
                    if (in_array($fresh->status, [
                        RefundStatus::Processing,
                        RefundStatus::Succeeded,
                        RefundStatus::Failed,
                        RefundStatus::Cancelled,
                        RefundStatus::RequiresReview,
                    ], true)) {
                        return $fresh;
                    }

                    return $this->refunds->dispatch($actor, $fresh, $request);
                },
            );
        } catch (LockTimeoutException $exception) {
            throw new ApiDomainException(
                'refund.dispatch_busy',
                'این بازپرداخت هم‌اکنون در حال پردازش است. کمی بعد دوباره بررسی کنید.',
                409,
                headers: ['Retry-After' => '2'],
                previous: $exception,
            );
        }
    }
}
