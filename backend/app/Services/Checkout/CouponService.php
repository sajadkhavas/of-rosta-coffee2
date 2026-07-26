<?php

namespace App\Services\Checkout;

use App\Enums\CouponType;
use App\Exceptions\ApiDomainException;
use App\Models\Coupon;
use App\Models\Roastery;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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
        /** @var EloquentCollection<int, Roastery> $roasteries */
        $roasteries = new EloquentCollection([$roastery]);

        return $this->resolveMarketplace($code, $roasteries, $subtotal);
    }

    /**
     * @param  EloquentCollection<int, Roastery>  $roasteries
     * @return array{coupon: Coupon|null, discount: int}
     */
    public function resolveMarketplace(
        ?string $code,
        EloquentCollection $roasteries,
        int $subtotal,
    ): array {
        $normalized = strtoupper(trim((string) $code));
        if ($normalized === '') {
            return ['coupon' => null, 'discount' => 0];
        }

        $coupon = Coupon::query()
            ->where('code', $normalized)
            ->where('is_active', true)
            ->first();

        if (! $coupon instanceof Coupon) {
            throw new ApiDomainException(
                'checkout.coupon_invalid',
                'کد تخفیف معتبر نیست.',
                422,
            );
        }

        if ($coupon->roastery_id !== null) {
            if ($roasteries->count() !== 1 || $roasteries->first()?->id !== $coupon->roastery_id) {
                throw new ApiDomainException(
                    'checkout.coupon_scope_invalid',
                    'این کد تخفیف فقط برای سفارش همان روستری قابل استفاده است.',
                    409,
                );
            }
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
            CouponType::Percentage => $this->percentageDiscount(
                $subtotal,
                min(10_000, $coupon->value),
            ),
        };

        if ($coupon->maximum_discount !== null) {
            $discount = min($discount, $coupon->maximum_discount);
        }

        return ['coupon' => $coupon, 'discount' => max(0, $discount)];
    }

    private function percentageDiscount(int $subtotal, int $basisPoints): int
    {
        $whole = intdiv($subtotal, 10_000) * $basisPoints;
        $remainder = intdiv(($subtotal % 10_000) * $basisPoints, 10_000);

        return $whole + $remainder;
    }
}
