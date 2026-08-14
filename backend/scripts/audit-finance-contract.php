<?php

$root = dirname(__DIR__);
$files = [
    'refund_service' => file_get_contents($root.'/app/Services/Refunds/RefundService.php'),
    'dispatch_service' => file_get_contents($root.'/app/Services/Refunds/RefundDispatchService.php'),
    'provider_manager' => file_get_contents($root.'/app/Services/Refunds/RefundProviderManager.php'),
    'refund_model' => file_get_contents($root.'/app/Models/RefundAttempt.php'),
    'case_model' => file_get_contents($root.'/app/Models/FinancialReconciliationCase.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_07_23_080001_create_refunds_and_reconciliation.php'),
    'routes' => file_get_contents($root.'/routes/finance.php'),
    'config' => file_get_contents($root.'/config/rosta.php'),
    'env' => file_get_contents($root.'/.env.example'),
    'openapi' => file_get_contents(dirname($root).'/docs/openapi/rosta-v1-finance.yaml'),
    'policy_migration' => file_get_contents($root.'/database/migrations/2026_08_14_080000_create_financial_truth_policies.php'),
    'policy_service' => file_get_contents($root.'/app/Services/Finance/FinancialPolicyService.php'),
    'policy_resolver' => file_get_contents($root.'/app/Services/Finance/FinancialPolicyResolver.php'),
    'truth_engine' => file_get_contents($root.'/app/Services/Finance/FinancialTruthEngine.php'),
    'quote_service' => file_get_contents($root.'/app/Services/Checkout/QuoteService.php'),
    'order_service' => file_get_contents($root.'/app/Services/Checkout/OrderService.php'),
    'readiness' => file_get_contents($root.'/app/Console/Commands/BackendReadiness.php'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$gate(
    'refund_idempotency_and_amount_guard',
    str_contains($files['migration'], "unique(['order_id', 'idempotency_key'])")
        && str_contains($files['refund_service'], 'refund.idempotency_conflict')
        && str_contains($files['refund_service'], 'refund.amount_exceeds_available')
        && str_contains($files['refund_service'], "->sum('amount')"),
    'Refund requests must be replay-safe and may never reserve more than the verified payment balance.',
);

$gate(
    'refund_payloads_encrypted',
    str_contains($files['refund_model'], "'request_payload' => 'encrypted:array'")
        && str_contains($files['refund_model'], "'response_payload' => 'encrypted:array'")
        && str_contains($files['case_model'], "'details' => 'encrypted:array'")
        && str_contains($files['case_model'], "'resolution' => 'encrypted'"),
    'Provider payloads and administrator reconciliation details must remain encrypted at rest.',
);

$gate(
    'testing_provider_forbidden_in_production',
    str_contains($files['provider_manager'], 'refund.testing_provider_forbidden')
        && str_contains($files['provider_manager'], "! app()->environment('production')"),
    'The testing refund provider must fail closed in production.',
);

$gate(
    'provider_dispatch_serialized',
    str_contains($files['dispatch_service'], "Cache::lock('refund-dispatch:'")
        && str_contains($files['dispatch_service'], 'RefundStatus::Processing')
        && str_contains($files['routes'], 'AdminDispatchRefundController::class'),
    'Concurrent administrator requests must not dispatch the same provider refund twice.',
);

$gate(
    'dual_control_default',
    str_contains($files['refund_service'], 'refund.dual_control_required')
        && str_contains($files['config'], "ROSTA_REFUND_DUAL_CONTROL', true")
        && str_contains($files['env'], 'ROSTA_REFUND_DUAL_CONTROL=true'),
    'Refund approval should require a second administrator unless explicitly disabled for a controlled environment.',
);

$gate(
    'full_and_partial_refund_truth',
    str_contains($files['refund_service'], 'PaymentAttemptStatus::Refunded')
        && str_contains($files['refund_service'], 'OrderStatus::Refunded')
        && str_contains($files['refund_service'], 'SubOrderStatus::Refunded')
        && str_contains($files['refund_service'], 'partial_refund_balance_open'),
    'Full refunds settle payment/order/suborder together; partial refunds remain explicitly open for reconciliation.',
);

$gate(
    'administrator_only_finance_routes',
    str_contains($files['routes'], 'rosta.role:administrator')
        && str_contains($files['routes'], '/admin/finance/refunds')
        && str_contains($files['routes'], '/admin/finance/reconciliation')
        && str_contains($files['routes'], '/admin/refunds/{refundId}/dispatch'),
    'Refund and reconciliation operations must remain administrator-only.',
);

$gate(
    'refund_disabled_by_default',
    str_contains($files['env'], 'ROSTA_REFUND_ENABLED=false')
        && str_contains($files['env'], 'REFUND_DRIVER=disabled')
        && str_contains($files['config'], "env('REFUND_DRIVER', 'disabled')"),
    'Refund execution must remain disabled until staging acceptance and an approved operational provider are ready.',
);

$gate(
    'openapi_finance_contract',
    str_contains($files['openapi'], '/admin/orders/{orderId}/refunds:')
        && str_contains($files['openapi'], '/admin/refunds/{refundId}/approve:')
        && str_contains($files['openapi'], '/admin/refunds/{refundId}/dispatch:')
        && str_contains($files['openapi'], '/admin/finance/reconciliation/{caseId}:')
        && str_contains($files['openapi'], '/admin/finance/tax-policies:')
        && str_contains($files['openapi'], '/admin/finance/commission-policies:'),
    'All administrator finance endpoints must remain represented in the frozen API contract.',
);

$gate(
    'versioned_effective_policies_without_guessed_rates',
    str_contains($files['policy_migration'], "Schema::create('tax_policies'")
        && str_contains($files['policy_migration'], "Schema::create('commission_policies'")
        && str_contains($files['policy_migration'], "'status' => 'legacy_unknown'")
        && ! str_contains($files['config'], 'default_tax_rate')
        && ! str_contains($files['config'], 'default_commission_rate'),
    'Tax and commission truth must be effective-dated and legacy rows must be explicitly unknown, never guessed.',
);

$gate(
    'policy_dual_control_and_immutability',
    str_contains($files['policy_service'], 'finance.policy_dual_control')
        && str_contains($files['policy_service'], 'finance.policy_immutable')
        && str_contains($files['policy_service'], "'checksum' => $checksum")
        && str_contains($files['policy_service'], 'finance.policy.published'),
    'Publishing requires a second administrator and produces an audited immutable checksum.',
);

$gate(
    'integer_snapshots_and_ledger_conservation',
    str_contains($files['truth_engine'], 'MoneyMath')
        && str_contains($files['quote_service'], "'financial_snapshot'")
        && str_contains($files['order_service'], 'recordCommissionAllocation')
        && str_contains($files['order_service'], 'recordTaxLine')
        && str_contains($files['policy_migration'], "'commission_amount'"),
    'Quote, order, allocation and tax-line records must retain integer policy snapshots and separate platform commission.',
);

$gate(
    'production_finance_fail_closed',
    str_contains($files['policy_resolver'], "environment('production')")
        && str_contains($files['policy_resolver'], 'finance.policy_unavailable')
        && str_contains($files['readiness'], "'financial_policies'"),
    'Production checkout and readiness must fail closed when the effective policy pair is absent.',
);

$gate(
    'whole_bean_boundary',
    ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files)),
    'Financial workflows must never introduce grind selection or grind state.',
);

$failed = array_values(array_filter($gates, static fn (array $item): bool => ! $item['passed']));
file_put_contents($root.'/finance-contract-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'finance_contract=ready',
    'passed' => $failed === [],
    'gates' => $gates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, "Finance contract audit failed:\n");
    foreach ($failed as $item) {
        fwrite(STDERR, "- {$item['name']}: {$item['evidence']}\n");
    }
    exit(1);
}

echo 'Finance contract audit passed ('.count($gates)." gates).\n";
