<?php

$root = dirname(__DIR__);
$files = [
    'payment_manager' => file_get_contents($root.'/app/Services/Payments/PaymentProviderManager.php'),
    'payment_controller' => file_get_contents($root.'/app/Http/Controllers/Payments/PaymentController.php'),
    'outbox_service' => file_get_contents($root.'/app/Services/Notifications/NotificationOutboxService.php'),
    'inquiry_service' => file_get_contents($root.'/app/Services/Support/InquiryService.php'),
    'inquiry_model' => file_get_contents($root.'/app/Models/Inquiry.php'),
    'inquiry_migration' => file_get_contents($root.'/database/migrations/2026_07_22_210001_create_reviews_and_inquiries.php'),
    'media_migration' => file_get_contents($root.'/database/migrations/2026_07_22_220001_create_media_upload_intents.php'),
    'domain_exception' => file_get_contents($root.'/app/Exceptions/ApiDomainException.php'),
    'frontend_ci' => file_get_contents(dirname($root).'/.github/workflows/ci.yml'),
    'backend_ci' => file_get_contents(dirname($root).'/.github/workflows/backend-ci.yml'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$pullRequestBlock = '';
$insidePullRequest = false;
foreach (preg_split('/\R/', $files['frontend_ci']) ?: [] as $line) {
    if (preg_match('/^([A-Za-z0-9_-]+):(?:\s|$)/', $line, $match) === 1) {
        if ($match[1] === 'pull_request') {
            $insidePullRequest = true;
            continue;
        }
        if ($insidePullRequest) {
            break;
        }
    }
    if ($insidePullRequest) {
        $pullRequestBlock .= $line."\n";
    }
}

$gate(
    'testing_payment_forbidden_in_production',
    str_contains($files['payment_manager'], 'payment.testing_provider_forbidden')
        && str_contains($files['payment_manager'], "! app()->environment('production')")
        && str_contains($files['payment_manager'], "'testing' => \$this->testingProvider()"),
    'The testing payment adapter must fail closed for initiation, callback and verification in production.',
);

$gate(
    'payment_callback_url_fail_closed',
    str_contains($files['payment_controller'], '! is_array($parts)')
        && str_contains($files['payment_controller'], "isset(\$parts['user'])")
        && str_contains($files['payment_controller'], "isset(\$parts['fragment'])")
        && str_contains($files['payment_controller'], 'allowed_payment_redirect_hosts'),
    'Payment callbacks may redirect only to explicitly approved, credential-free URLs.',
);

$gate(
    'refund_pending_mapped_at_public_boundary',
    str_contains($files['payment_controller'], 'OrderStatus::RefundPending')
        && str_contains($files['payment_controller'], 'OrderStatus::Processing->value')
        && str_contains($files['payment_controller'], 'publicOrderStatus'),
    'Internal refund_pending truth must be mapped to the frozen public order contract at one backend boundary.',
);

$gate(
    'outbox_concurrency_safe',
    str_contains($files['outbox_service'], 'createOrFirst')
        && str_contains($files['outbox_service'], 'NotificationStatus::Processing')
        && str_contains($files['outbox_service'], 'وضعیت Outbox هنگام ثبت ارسال معتبر نیست'),
    'Notification deduplication and final state writes must remain race-safe.',
);

$gate(
    'inquiry_concurrency_safe',
    str_contains($files['inquiry_service'], 'createOrFirst')
        && str_contains($files['inquiry_service'], 'deduplicationKey')
        && str_contains($files['inquiry_model'], "'deduplication_key'")
        && str_contains($files['inquiry_migration'], "deduplication_key', 64)->unique()"),
    'Concurrent duplicate inquiries must collapse to one persisted reference.',
);

$gate(
    'media_mysql_index_safe',
    str_contains($files['media_migration'], "object_key', 512)->unique()")
        && ! str_contains($files['media_migration'], "object_key', 1000)->unique()"),
    'The unique media object key index must remain within MySQL utf8mb4 index limits.',
);

$gate(
    'domain_exception_preserves_cause',
    str_contains($files['domain_exception'], '?Throwable $previous = null')
        && str_contains($files['domain_exception'], 'parent::__construct($message, 0, $previous)'),
    'Infrastructure exceptions wrapped as domain failures must retain their original cause.',
);

$gate(
    'stacked_pr_ci_enabled',
    str_contains($files['frontend_ci'], "pull_request:\n")
        && ! str_contains($pullRequestBlock, 'branches:')
        && str_contains($files['backend_ci'], 'docs/openapi/**')
        && str_contains($files['backend_ci'], 'rosta-v1-commerce-additions.yaml')
        && str_contains($files['backend_ci'], 'workflow_dispatch:'),
    'Frontend and backend quality gates must run for stacked PRs and all OpenAPI contracts.',
);

$gate(
    'whole_bean_boundary',
    ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files)),
    'Validation hardening must not introduce grind selection or grind state.',
);

$failed = array_values(array_filter($gates, static fn (array $item): bool => ! $item['passed']));
file_put_contents($root.'/validation-hardening-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'validation_hardening=ready',
    'passed' => $failed === [],
    'gates' => $gates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, "Validation hardening audit failed:\n");
    foreach ($failed as $item) {
        fwrite(STDERR, "- {$item['name']}: {$item['evidence']}\n");
    }
    exit(1);
}

echo 'Validation hardening audit passed ('.count($gates)." gates).\n";
