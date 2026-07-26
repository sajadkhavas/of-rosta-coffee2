<?php

namespace App\Services\Catalog;

use App\Enums\PackagingFeeMode;
use App\Exceptions\ApiDomainException;
use App\Models\Product;

final class ProductPackagingPolicy
{
    private const int MAX_MONEY = 9_007_199_254_740_991;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data, ?Product $product = null): array
    {
        $rawMode = $data['packaging_fee_mode']
            ?? $product->packaging_fee_mode
            ?? PackagingFeeMode::Free;
        $mode = $rawMode instanceof PackagingFeeMode
            ? $rawMode
            : PackagingFeeMode::tryFrom((string) $rawMode);

        if (! $mode instanceof PackagingFeeMode) {
            throw new ApiDomainException(
                'catalog.packaging_mode_invalid',
                'نوع هزینه بسته‌بندی معتبر نیست.',
                422,
                ['packaging_fee_mode' => ['نوع هزینه بسته‌بندی معتبر نیست.']],
            );
        }

        $amount = array_key_exists('packaging_fee_amount', $data)
            ? (int) $data['packaging_fee_amount']
            : (int) ($product->packaging_fee_amount ?? 0);

        if ($amount < 0 || $amount > self::MAX_MONEY) {
            throw new ApiDomainException(
                'catalog.packaging_amount_invalid',
                'مبلغ بسته‌بندی خارج از محدوده مجاز است.',
                422,
                ['packaging_fee_amount' => ['مبلغ بسته‌بندی خارج از محدوده مجاز است.']],
            );
        }

        if ($mode === PackagingFeeMode::Free) {
            $amount = 0;
        } elseif ($amount < 1) {
            throw new ApiDomainException(
                'catalog.packaging_amount_required',
                'برای بسته‌بندی پولی باید مبلغی بیشتر از صفر ثبت شود.',
                422,
                ['packaging_fee_amount' => ['برای بسته‌بندی پولی مبلغ الزامی است.']],
            );
        }

        $data['packaging_fee_mode'] = $mode->value;
        $data['packaging_fee_amount'] = $amount;

        return $data;
    }

    /** @return array{mode: string, fee_amount: int, currency: string, is_free: bool, label: string} */
    public function snapshot(Product $product): array
    {
        $amount = $product->packagingFee();

        return [
            'mode' => $product->packaging_fee_mode->value,
            'fee_amount' => $amount,
            'currency' => 'IRR',
            'is_free' => $amount === 0,
            'label' => $amount === 0
                ? 'بسته‌بندی روستری رایگان'
                : 'هزینه بسته‌بندی روستری',
        ];
    }
}
