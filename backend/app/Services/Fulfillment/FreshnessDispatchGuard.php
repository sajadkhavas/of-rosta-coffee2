<?php

namespace App\Services\Fulfillment;

use App\Exceptions\ApiDomainException;
use App\Models\OrderItem;
use App\Models\RoastBatch;
use App\Models\SubOrder;

final class FreshnessDispatchGuard
{
    public function assertDispatchable(SubOrder $subOrder): void
    {
        $maxAgeDays = config('freshness.max_dispatch_roast_age_days');
        if (! is_int($maxAgeDays) || $maxAgeDays < 1) {
            throw new ApiDomainException(
                'freshness.policy_unconfigured',
                'سیاست حداکثر سن بچ رست برای ارسال هنوز در محیط Production تأیید نشده است.',
                503,
            );
        }

        $items = OrderItem::query()
            ->where('sub_order_id', $subOrder->id)
            ->with('roastBatch')
            ->lockForUpdate()
            ->get();

        if ($items->isEmpty()) {
            throw new ApiDomainException(
                'freshness.order_items_missing',
                'برای کنترل تازگی، آیتم معتبر در زیرسفارش پیدا نشد.',
                409,
            );
        }

        foreach ($items as $item) {
            $batch = $item->roastBatch;
            if (! $batch instanceof RoastBatch || $item->roast_batch_id === null) {
                throw new ApiDomainException(
                    'freshness.roast_batch_required',
                    'ارسال قهوه بدون بچ رست قابل ردیابی مجاز نیست.',
                    409,
                    ['order_item_id' => [$item->id]],
                );
            }

            if (! $batch->is_active) {
                throw new ApiDomainException(
                    'freshness.roast_batch_inactive',
                    'بچ رست این سفارش دیگر برای ارسال فعال نیست.',
                    409,
                    ['roast_batch_id' => [$batch->id]],
                );
            }

            if ($batch->roasted_at->isFuture()) {
                throw new ApiDomainException(
                    'freshness.roasted_at_invalid',
                    'تاریخ رست بچ انتخاب‌شده معتبر نیست.',
                    409,
                    ['roast_batch_id' => [$batch->id]],
                );
            }

            if ($batch->available_from?->isFuture()) {
                throw new ApiDomainException(
                    'freshness.batch_not_available',
                    'این بچ رست هنوز وارد بازه مجاز ارسال نشده است.',
                    409,
                    ['roast_batch_id' => [$batch->id]],
                );
            }

            if ($batch->roasted_at->lt(now()->subDays($maxAgeDays))) {
                throw new ApiDomainException(
                    'freshness.roast_batch_too_old',
                    'سن بچ رست از سقف تازگی مورد پذیرش رستا عبور کرده است؛ بچ تازه‌تری انتخاب کنید.',
                    409,
                    [
                        'roast_batch_id' => [$batch->id],
                        'max_dispatch_roast_age_days' => [$maxAgeDays],
                    ],
                );
            }
        }
    }
}
