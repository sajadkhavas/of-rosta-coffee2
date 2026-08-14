<?php

namespace App\Services\Finance;

use App\Models\CommissionPolicyRule;
use App\Models\CommissionPolicy;
use App\Models\TaxPolicy;
use App\Models\TaxPolicyRule;
use Carbon\CarbonInterface;

final class FinancialTruthEngine
{
    public function __construct(
        private readonly FinancialPolicyResolver $policies,
        private readonly MoneyMath $money,
    ) {}

    /**
     * @param  list<array{key: string, component: string, owner_type: string, gross_amount: int, discount_amount?: int, attributes?: array<string, mixed>}>  $components
     * @return array{components: array<string, array<string, mixed>>, totals: array<string, int>, snapshot: array<string, mixed>}
     */
    public function calculate(array $components, CarbonInterface $at, string $currency = 'IRR'): array
    {
        $pair = $this->policies->resolve($at, $currency);
        if ($pair === null) {
            return $this->legacy($components, $at, $currency);
        }

        $calculated = [];
        $totals = ['gross' => 0, 'discount' => 0, 'tax' => 0, 'commission' => 0, 'payable' => 0];
        $policySnapshot = [
            'tax' => $this->taxSnapshot($pair['tax']),
            'commission' => $this->commissionSnapshot($pair['commission']),
        ];
        foreach ($components as $component) {
            $gross = $component['gross_amount'];
            $discount = $component['discount_amount'] ?? 0;
            $base = $gross - $discount;
            $taxRule = $pair['tax']->rules->first(
                fn (TaxPolicyRule $rule): bool => $this->matches($rule->component, $rule->applicability, $component),
            );
            $commissionRule = $component['owner_type'] === 'rosta'
                ? null
                : $pair['commission']->rules->first(
                    fn (CommissionPolicyRule $rule): bool => $this->matchesCommission($rule, $component),
                );
            $tax = $taxRule instanceof TaxPolicyRule
                ? $this->money->basisPoints($base, $taxRule->rate_basis_points, $pair['tax']->rounding_mode)
                : 0;
            $commission = $commissionRule instanceof CommissionPolicyRule
                ? $this->money->basisPoints($base, $commissionRule->rate_basis_points, $pair['commission']->rounding_mode)
                : 0;
            $payable = $base + $tax - $commission;

            $calculated[$component['key']] = [
                ...$component,
                'discount_amount' => $discount,
                'taxable_amount' => $base,
                'tax_amount' => $tax,
                'commission_amount' => $commission,
                'payable_amount' => $payable,
                'tax_rule' => $taxRule instanceof TaxPolicyRule ? [
                    'code' => $taxRule->code,
                    'jurisdiction' => $taxRule->jurisdiction,
                    'rate_basis_points' => $taxRule->rate_basis_points,
                ] : null,
                'commission_rule' => $commissionRule instanceof CommissionPolicyRule ? [
                    'code' => $commissionRule->code,
                    'rate_basis_points' => $commissionRule->rate_basis_points,
                ] : null,
                'policy_snapshot' => $policySnapshot,
            ];
            $totals['gross'] += $gross;
            $totals['discount'] += $discount;
            $totals['tax'] += $tax;
            $totals['commission'] += $commission;
            $totals['payable'] += $payable;
        }

        return [
            'components' => $calculated,
            'totals' => $totals,
            'snapshot' => [
                'status' => 'authoritative',
                'calculation_version' => 'ps4a-financial-truth-v1',
                'calculated_at' => $at->toIso8601String(),
                'currency' => $currency,
                'tax_policy' => $this->taxSnapshot($pair['tax']),
                'commission_policy' => $this->commissionSnapshot($pair['commission']),
            ],
        ];
    }

    /** @param array<string, mixed>|null $applicability */
    private function matches(string $ruleComponent, ?array $applicability, array $component): bool
    {
        if ($ruleComponent !== $component['component']) {
            return false;
        }
        $attributes = $component['attributes'] ?? [];
        foreach (['roastery_ids', 'product_ids', 'provider_types'] as $filter) {
            if (! isset($applicability[$filter]) || ! is_array($applicability[$filter])) {
                continue;
            }
            $attribute = match ($filter) {
                'roastery_ids' => 'roastery_id',
                'product_ids' => 'product_id',
                default => 'provider_type',
            };
            if (! in_array($attributes[$attribute] ?? null, $applicability[$filter], true)) {
                return false;
            }
        }

        return true;
    }

    private function matchesCommission(CommissionPolicyRule $rule, array $component): bool
    {
        return ($rule->owner_type === null || $rule->owner_type === $component['owner_type'])
            && $this->matches($rule->component, $rule->applicability, $component);
    }

    /** @return array<string, mixed> */
    private function taxSnapshot(TaxPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'version' => $policy->version,
            'checksum' => $policy->checksum,
            'rounding_mode' => $policy->rounding_mode,
            'effective_from' => $policy->effective_from?->toIso8601String(),
            'effective_to' => $policy->effective_to?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function commissionSnapshot(CommissionPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'version' => $policy->version,
            'checksum' => $policy->checksum,
            'rounding_mode' => $policy->rounding_mode,
            'effective_from' => $policy->effective_from?->toIso8601String(),
            'effective_to' => $policy->effective_to?->toIso8601String(),
        ];
    }

    private function legacy(array $components, CarbonInterface $at, string $currency): array
    {
        $calculated = [];
        $totals = ['gross' => 0, 'discount' => 0, 'tax' => 0, 'commission' => 0, 'payable' => 0];
        foreach ($components as $component) {
            $discount = $component['discount_amount'] ?? 0;
            $payable = $component['gross_amount'] - $discount;
            $calculated[$component['key']] = [
                ...$component,
                'discount_amount' => $discount,
                'taxable_amount' => $payable,
                'tax_amount' => 0,
                'commission_amount' => 0,
                'payable_amount' => $payable,
                'tax_rule' => null,
                'commission_rule' => null,
                'policy_snapshot' => [
                    'tax' => ['status' => 'legacy_unknown'],
                    'commission' => ['status' => 'legacy_unknown'],
                ],
            ];
            $totals['gross'] += $component['gross_amount'];
            $totals['discount'] += $discount;
            $totals['payable'] += $payable;
        }

        return [
            'components' => $calculated,
            'totals' => $totals,
            'snapshot' => [
                'status' => 'legacy_unknown',
                'version' => 'legacy-pre-policy-non-production',
                'calculated_at' => $at->toIso8601String(),
                'currency' => $currency,
                'reason' => 'No rate is inferred; production fails closed until both policies are published.',
            ],
        ];
    }
}
