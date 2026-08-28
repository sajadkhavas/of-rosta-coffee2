<?php

$root = dirname(__DIR__);

$files = [
    'enum' => file_get_contents($root.'/app/Enums/SettlementBatchStatus.php'),
    'model' => file_get_contents($root.'/app/Models/SettlementBatch.php'),
    'service' => file_get_contents($root.'/app/Services/Settlement/SettlementBatchService.php'),
    'request' => file_get_contents($root.'/app/Http/Requests/Settlement/ResolveSettlementBatchRequest.php'),
    'reconciliation' => file_get_contents($root.'/app/Services/Finance/FinancialReconciliationService.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_08_28_120001_harden_ps4b_refund_payout_reconciliation.php'),
    'openapi' => file_get_contents(dirname($root).'/docs/openapi/rosta-v1-finance.yaml'),
    'acceptance' => file_get_contents(dirname($root).'/docs/PS4_2_REFUND_PAYOUT_RECONCILIATION.md'),
];

$rules = [
    'requires review state' => str_contains($files['enum'], "RequiresReview = 'requires_review'"),
    'maker checker columns' => str_contains($files['migration'], "confirmed_by_id") && str_contains($files['model'], "confirmed_by_id"),
    'encrypted evidence' => str_contains($files['model'], "'payout_evidence' => 'encrypted:array'"),
    'dual control' => str_contains($files['service'], 'settlement.payout_dual_control_required'),
    'idempotent paid replay' => str_contains($files['service'], 'settlement.payout_idempotency_conflict'),
    'amount evidence guard' => str_contains($files['service'], 'settlement.payout_evidence_amount_mismatch'),
    'unknown outcome reconciliation' => str_contains($files['service'], 'settlement_payout_unknown_outcome'),
    'per-order reconciliation' => str_contains($files['reconciliation'], 'openSettlementBatchReview'),
    'review action validation' => str_contains($files['request'], "['process', 'paid', 'failed', 'review']"),
    'openapi evidence contract' => str_contains($files['openapi'], 'SettlementPayoutEvidence'),
    'provider truth contract' => str_contains($files['acceptance'], 'manual-evidence'),
    'rollback evidence guard' => str_contains($files['migration'], 'rollback refused because payout confirmation evidence exists'),
];

$failed = array_keys(array_filter($rules, static fn (bool $passed): bool => ! $passed));
file_put_contents($root.'/ps4b-refund-payout-reconciliation-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'ps4b_refund_payout_reconciliation=clean',
    'passed' => $failed === [],
    'rules' => $rules,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, "PS4.2 refund/payout/reconciliation contract audit failed.\n");
    foreach ($failed as $rule) {
        fwrite(STDERR, "- {$rule}\n");
    }
    exit(1);
}

echo 'PS4.2 refund/payout/reconciliation contract audit passed ('.count($rules)." rules).\n";
