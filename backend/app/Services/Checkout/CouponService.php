<?php

namespace App\Services\Checkout;

use App\Enums\CouponType;
use App\Exceptions\ApiDomainException;
use App\Models\Coupon;
use App\Models\Roastery;

final class CouponService
{
    /**
     * @return array{coupon: Coupon|null, discount: int}
     */
    public function resolve(
        ?string $code,
        Roastery $roastery,
        int $subtotal,
    ): array {
        $normalized = strtoupper(trim((string) $code));
        if ($normalized === '') {
            return ['coupon' => null, 'discount' => 0];
        }

        $coupon = Coupon::query()
            ->where('code', $normalized)
            ->where('is_active', true)
            ->where(static function ($query) use ($roastery): void {
                $query->whereNull('roastery_id')
                    ->orWhere('roastery_id', $roastery->id);
            })
            ->first();

        if (! $coupon instanceof Coupon) {
            throw new ApiDomainException(
                'checkout.coupon_invalid',
                'کد تخفیف معتبر نیست.',
                422,
            );
        }

        if (
            ($coupon->starts_at !== null && $coupon->starts_at->isFuture())
            || ($coupon->ends_at !== null && $coupon->ends_at->isPast())
            || ($coupon->max_redemptions !== null && $coupon->redemption_count >= $coupon->max_redemptions)
        ) {
            throw new ApiDomainException(
                'checkout.coupon_unavailable',
                'کد تخفیف در حال حاضر قابل استفاده نیست.',
                409,
            );
        }

        if ($subtotal < $coupon->minimum_subtotal) {
            throw new ApiDomainException(
                'checkout.coupon_minimum_not_met',
                'حداقل مبلغ لازم برای این کد تخفیف تأمین نشده است.',
                409,
            );
        }

        $discount = match ($coupon->type) {
            CouponType::Fixed => min($subtotal, $coupon->value),
            CouponType::Percentage => intdiv($subtotal * min(10_000, $coupon->value), 10_000),
        };

        if ($coupon->maximum_discount !== null) {
            $discount = min($discount, $coupon->maximum_discount);
        }

        return ['coupon' => $coupon, 'discount' => max(0, $discount)];
    }
}
