<?php

$backendRoot = dirname(__DIR__);
$repoRoot = dirname($backendRoot);

/**
 * @return string
 */
function requiredFile(string $path): string
{
    if (! is_file($path)) {
        fwrite(STDERR, "Required PS8B evidence file is missing: {$path}\n");
        exit(1);
    }

    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "Unable to read PS8B evidence file: {$path}\n");
        exit(1);
    }

    return $content;
}

$files = [
    'payment_service' => requiredFile($backendRoot.'/app/Services/Payments/PaymentService.php'),
    'payment_manager' => requiredFile($backendRoot.'/app/Services/Payments/PaymentProviderManager.php'),
    'refund_service' => requiredFile($backendRoot.'/app/Services/Refunds/RefundService.php'),
    'refund_manager' => requiredFile($backendRoot.'/app/Services/Refunds/RefundProviderManager.php'),
    'settlement_service' => requiredFile($backendRoot.'/app/Services/Settlement/SettlementBatchService.php'),
    'reconciliation' => requiredFile($backendRoot.'/app/Services/Finance/FinancialReconciliationService.php'),
    'finance_openapi' => requiredFile($repoRoot.'/docs/openapi/rosta-v1-finance.yaml'),
    'provider_truth' => requiredFile($repoRoot.'/docs/PS4_2_REFUND_PAYOUT_RECONCILIATION.md'),
    'composer' => requiredFile($backendRoot.'/composer.json'),
];

$requiredEvidence = [
    $backendRoot.'/scripts/audit-finance-contract.php',
    $backendRoot.'/scripts/audit-ps4a-financial-truth.php',
    $backendRoot.'/scripts/audit-ps4b-refund-payout-reconciliation.php',
    $backendRoot.'/scripts/audit-ps6b-backend-observability.php',
    $backendRoot.'/scripts/audit-r5i-delivery-settlement.php',
    $backendRoot.'/tests/Feature/CheckoutFinancialBoundariesTest.php',
    $backendRoot.'/tests/Feature/FinancialTruthPolicyTest.php',
    $backendRoot.'/tests/Feature/PaymentLifecycleTest.php',
    $backendRoot.'/tests/Feature/RefundWorkflowTest.php',
    $backendRoot.'/tests/Feature/R5IDeliverySettlementTest.php',
    $backendRoot.'/tests/Feature/TransactionalCheckoutTest.php',
    $backendRoot.'/tests/Feature/PS6BBackendObservabilityTest.php',
];

$rules = [
    'finance acceptance evidence set is complete' => array_all(
        $requiredEvidence,
        static fn (string $path): bool => is_file($path),
    ),
    'payment mutation is transactional and locked' => str_contains($files['payment_service'], 'DB::transaction')
        && str_contains($files['payment_service'], '->lockForUpdate()'),
    'payment idempotency conflicts fail closed' => str_contains(
        $files['payment_service'],
        'payment.idempotency_conflict',
    ),
    'verified payment rechecks server amount and currency' => str_contains(
        $files['payment_service'],
        '$lockedAttempt->amount !== $order->grand_total',
    ) && str_contains($files['payment_service'], '$lockedAttempt->currency !== $order->currency'),
    'payment provider is disabled by default' => str_contains(
        $files['payment_manager'],
        "config('rosta.payment.provider', 'disabled')",
    ) && str_contains($files['payment_manager'], "config('rosta.payment.enabled', false)"),
    'testing payment provider is forbidden in production' => str_contains(
        $files['payment_manager'],
        'payment.testing_provider_forbidden',
    ),
    'refund mutation is transactional and locked' => str_contains($files['refund_service'], 'DB::transaction')
        && str_contains($files['refund_service'], '->lockForUpdate()'),
    'refund idempotency conflicts fail closed' => str_contains(
        $files['refund_service'],
        'refund.idempotency_conflict',
    ),
    'refund approval defaults to dual control' => str_contains(
        $files['refund_service'],
        "config('rosta.refund.require_dual_control', true)",
    ) && str_contains($files['refund_service'], 'refund.dual_control_required'),
    'unknown refund outcomes open reconciliation' => str_contains(
        $files['refund_service'],
        'openRefundReview',
    ) && str_contains($files['refund_service'], 'refund_unknown_outcome'),
    'refund provider is disabled by default' => str_contains(
        $files['refund_manager'],
        "config('rosta.refund.provider', 'disabled')",
    ) && str_contains($files['refund_manager'], "config('rosta.refund.enabled', false)"),
    'testing refund provider is forbidden in production' => str_contains(
        $files['refund_manager'],
        'refund.testing_provider_forbidden',
    ),
    'refund manager does not fabricate a live zarinpal adapter' => ! str_contains(
        $files['refund_manager'],
        "'zarinpal'",
    ),
    'settlement mutation is transactional and locked' => str_contains(
        $files['settlement_service'],
        'DB::transaction',
    ) && str_contains($files['settlement_service'], '->lockForUpdate()'),
    'settlement payout defaults to maker checker control' => str_contains(
        $files['settlement_service'],
        "config('rosta.settlement.require_payout_dual_control', true)",
    ) && str_contains($files['settlement_service'], 'settlement.payout_dual_control_required'),
    'settlement paid replay is evidence-idempotent' => str_contains(
        $files['settlement_service'],
        'settlement.payout_idempotency_conflict',
    ),
    'settlement evidence amount and currency must match' => str_contains(
        $files['settlement_service'],
        'settlement.payout_evidence_amount_mismatch',
    ),
    'unknown payout outcomes open reconciliation' => str_contains(
        $files['settlement_service'],
        'settlement_payout_unknown_outcome',
    ) && str_contains($files['settlement_service'], 'openSettlementBatchReview'),
    'financial reconciliation remains an explicit service boundary' => str_contains(
        $files['reconciliation'],
        'final class FinancialReconciliationService',
    ),
    'finance openapi keeps settlement payout evidence contract' => str_contains(
        $files['finance_openapi'],
        'SettlementPayoutEvidence',
    ),
    'provider truth remains manual evidence until entitlement is proven' => str_contains(
        $files['provider_truth'],
        'manual-evidence payout',
    ) && str_contains($files['provider_truth'], 'No live payout transfer endpoint'),
    'aggregate backend gate includes prior finance acceptance audits' => str_contains(
        $files['composer'],
        '@audit:finance',
    ) && str_contains($files['composer'], '@audit:ps4a')
        && str_contains($files['composer'], '@audit:ps4b')
        && str_contains($files['composer'], '@audit:r5i')
        && str_contains($files['composer'], '@audit:ps6b'),
];

$failed = array_keys(array_filter(
    $rules,
    static fn (bool $passed): bool => ! $passed,
));

$result = [
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'ps8b_backend_finance_acceptance=clean',
    'passed' => $failed === [],
    'rules' => $rules,
];

file_put_contents(
    $backendRoot.'/ps8b-backend-finance-acceptance-audit.json',
    json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
);

if ($failed !== []) {
    fwrite(STDERR, "PS8B backend/finance acceptance audit failed.\n");
    foreach ($failed as $rule) {
        fwrite(STDERR, "- {$rule}\n");
    }

    exit(1);
}

echo 'ROSTA_PS8B_BACKEND_FINANCE_ACCEPTANCE_CLEAN ('.count($rules)." rules)\n";
