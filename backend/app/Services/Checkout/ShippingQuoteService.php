<?php

namespace App\Services\Checkout;

use App\Exceptions\ApiDomainException;
use App\Models\Address;
use App\Models\Roastery;
use App\Models\ShippingRule;
use Illuminate\Database\Eloquent\Builder;

final class ShippingQuoteService
{
    /**
     * @return array{total: int, snapshot: array<string, mixed>}
     */
    public function quote(
        Roastery $roastery,
        Address $address,
        int $subtotal,
    ): array {
        $rule = ShippingRule::query()
            ->where('roastery_id', $roastery->id)
            ->where('is_active', true)
            ->where(static function (Builder $query) use ($address): void {
                $query->whereNull('province')
                    ->orWhere('province', $address->province);
            })
            ->where(static function (Builder $query) use ($address): void {
                $query->whereNull('city')
                    ->orWhere('city', $address->city);
            })
            ->orderByRaw('CASE WHEN city IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN province IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('priority')
            ->first();

        if (! $rule instanceof ShippingRule) {
            throw new ApiDomainException(
                'checkout.delivery_unavailable',
                'ارسال به این آدرس برای روستری انتخاب‌شده فعال نیست.',
                409,
            );
        }

        $total = $rule->free_over !== null && $subtotal >= $rule->free_over
            ? 0
            : $rule->base_cost;

        return [
            'total' => $total,
            'snapshot' => [
                'rule_id' => $rule->id,
                'province' => $rule->province,
                'city' => $rule->city,
                'base_cost' => $rule->base_cost,
                'free_over' => $rule->free_over,
                'quoted_total' => $total,
            ],
        ];
    }
}
