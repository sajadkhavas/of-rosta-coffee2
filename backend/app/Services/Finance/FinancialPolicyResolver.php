<?php

namespace App\Services\Finance;

use App\Exceptions\ApiDomainException;
use App\Models\CommissionPolicy;
use App\Models\TaxPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class FinancialPolicyResolver
{
    /**
     * @return array{tax: TaxPolicy, commission: CommissionPolicy}|null
     */
    public function resolve(CarbonInterface $at, string $currency = 'IRR'): ?array
    {
        if (! Schema::hasTable('tax_policies') || ! Schema::hasTable('commission_policies')) {
            return $this->unavailable('Financial policy schema is not current.');
        }

        $tax = $this->active(TaxPolicy::query(), $at, $currency)->get();
        $commission = $this->active(CommissionPolicy::query(), $at, $currency)->get();

        if ($tax->count() === 1 && $commission->count() === 1) {
            /** @var TaxPolicy $taxPolicy */
            $taxPolicy = $tax->first();
            /** @var CommissionPolicy $commissionPolicy */
            $commissionPolicy = $commission->first();

            return [
                'tax' => $taxPolicy->load('rules'),
                'commission' => $commissionPolicy->load('rules'),
            ];
        }

        if ($tax->count() > 1 || $commission->count() > 1) {
            throw new ApiDomainException(
                'finance.policy_ambiguous',
                'بیش از یک سیاست مالی برای زمان سفارش فعال است.',
                503,
            );
        }

        return $this->unavailable('An active tax and commission policy pair is required.');
    }

    public function ready(): bool
    {
        try {
            return $this->resolve(now()->toImmutable()) !== null || ! $this->required();
        } catch (Throwable) {
            return false;
        }
    }

    public function required(): bool
    {
        return app()->environment('production') || (bool) config('rosta.finance.require_policies', false);
    }

    /** @param  Builder<TaxPolicy>|Builder<CommissionPolicy>  $query */
    private function active(Builder $query, CarbonInterface $at, string $currency): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('currency', $currency)
            ->whereNotNull('checksum')
            ->where('effective_from', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('version');
    }

    private function unavailable(string $reason): null
    {
        if ($this->required()) {
            throw new ApiDomainException(
                'finance.policy_unavailable',
                'سیاست مالی معتبر برای زمان سفارش فعال نیست.',
                503,
                ['policy' => [$reason]],
            );
        }

        return null;
    }
}
