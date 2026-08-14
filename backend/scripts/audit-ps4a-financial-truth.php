<?php

$root = dirname(__DIR__);
$evidence = [
    'migration' => file_get_contents($root.'/database/migrations/2026_08_14_080000_create_financial_truth_policies.php'),
    'engine' => file_get_contents($root.'/app/Services/Finance/FinancialTruthEngine.php'),
    'resolver' => file_get_contents($root.'/app/Services/Finance/FinancialPolicyResolver.php'),
    'policy' => file_get_contents($root.'/app/Services/Finance/FinancialPolicyService.php'),
    'quote' => file_get_contents($root.'/app/Services/Checkout/QuoteService.php'),
    'order' => file_get_contents($root.'/app/Services/Checkout/OrderService.php'),
    'routes' => file_get_contents($root.'/routes/finance.php'),
    'openapi' => file_get_contents(dirname($root).'/docs/openapi/rosta-v1-finance.yaml'),
];

$gates = [
    'effective_dated_pair' => str_contains($evidence['migration'], 'tax_policy_active_lookup')
        && str_contains($evidence['migration'], 'commission_policy_active_lookup')
        && str_contains($evidence['resolver'], "where('effective_from', '<=', \$at)"),
    'no_inferred_production_rate' => str_contains($evidence['resolver'], 'finance.policy_unavailable')
        && str_contains($evidence['engine'], 'legacy-pre-policy-non-production'),
    'dual_control' => str_contains($evidence['policy'], 'finance.policy_dual_control')
        && str_contains($evidence['policy'], "'checksum' => \$checksum"),
    'immutable_snapshots' => str_contains($evidence['quote'], "'financial_snapshot'")
        && str_contains($evidence['order'], "'financial_snapshot'"),
    'ledger_conservation' => str_contains($evidence['order'], "'allocation_type' => 'commission'")
        && str_contains($evidence['order'], 'recordTaxLine'),
    'admin_and_openapi' => str_contains($evidence['routes'], '/admin/finance/tax-policies')
        && str_contains($evidence['routes'], '/admin/finance/commission-policies')
        && str_contains($evidence['openapi'], 'publishTaxPolicy')
        && str_contains($evidence['openapi'], 'publishCommissionPolicy'),
];

$failed = array_keys(array_filter($gates, static fn (bool $passed): bool => ! $passed));
file_put_contents($root.'/ps4a-financial-truth-audit.json', json_encode([
    'marker' => 'ps4a_financial_truth=ready',
    'passed' => $failed === [],
    'gates' => $gates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, 'PS4A financial truth audit failed: '.implode(', ', $failed).PHP_EOL);
    exit(1);
}

echo 'PS4A financial truth audit passed ('.count($gates)." gates).\n";
