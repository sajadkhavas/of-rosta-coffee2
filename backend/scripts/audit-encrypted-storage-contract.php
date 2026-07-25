<?php

$root = dirname(__DIR__);
$failures = [];

$contracts = [
    'app/Models/AuditLog.php:metadata' => [
        'migration' => 'database/migrations/2026_07_21_000002_create_audit_logs_table.php',
        'field' => 'metadata',
    ],
    'app/Models/CheckoutQuote.php:address_snapshot' => [
        'migration' => 'database/migrations/2026_07_21_030001_create_transactional_checkout_tables.php',
        'field' => 'address_snapshot',
    ],
    'app/Models/Order.php:address_snapshot' => [
        'migration' => 'database/migrations/2026_07_21_030001_create_transactional_checkout_tables.php',
        'field' => 'address_snapshot',
    ],
    'app/Models/OrderEvent.php:internal_metadata' => [
        'migration' => 'database/migrations/2026_07_25_230001_create_r5b_marketplace_schema.php',
        'field' => 'internal_metadata',
    ],
    'app/Models/ShipmentLeg.php:destination_snapshot' => [
        'migration' => 'database/migrations/2026_07_25_230001_create_r5b_marketplace_schema.php',
        'field' => 'destination_snapshot',
    ],
    'app/Models/ShipmentLeg.php:origin_snapshot' => [
        'migration' => 'database/migrations/2026_07_25_230001_create_r5b_marketplace_schema.php',
        'field' => 'origin_snapshot',
    ],
    'app/Models/StockLedgerEntry.php:metadata' => [
        'migration' => 'database/migrations/2026_07_21_020001_create_catalog_inventory_tables.php',
        'field' => 'metadata',
    ],
    'app/Models/SubOrderStatusHistory.php:metadata' => [
        'migration' => 'database/migrations/2026_07_22_200001_create_fulfillment_tables.php',
        'field' => 'metadata',
    ],
    'app/Models/NotificationOutbox.php:payload' => [
        'migration' => 'database/migrations/2026_07_22_190002_create_notification_outbox.php',
        'field' => 'payload',
    ],
    'app/Models/PaymentAttempt.php:request_payload' => [
        'migration' => 'database/migrations/2026_07_22_190001_create_payment_attempts.php',
        'field' => 'request_payload',
    ],
    'app/Models/PaymentAttempt.php:response_payload' => [
        'migration' => 'database/migrations/2026_07_22_190001_create_payment_attempts.php',
        'field' => 'response_payload',
    ],
    'app/Models/PaymentAttempt.php:verification_payload' => [
        'migration' => 'database/migrations/2026_07_22_190001_create_payment_attempts.php',
        'field' => 'verification_payload',
    ],
    'app/Models/RefundAttempt.php:request_payload' => [
        'migration' => 'database/migrations/2026_07_23_080001_create_refunds_and_reconciliation.php',
        'field' => 'request_payload',
    ],
    'app/Models/RefundAttempt.php:response_payload' => [
        'migration' => 'database/migrations/2026_07_23_080001_create_refunds_and_reconciliation.php',
        'field' => 'response_payload',
    ],
    'app/Models/FinancialReconciliationCase.php:details' => [
        'migration' => 'database/migrations/2026_07_23_080001_create_refunds_and_reconciliation.php',
        'field' => 'details',
    ],
];

$discovered = [];
$models = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/app/Models', FilesystemIterator::SKIP_DOTS),
);

foreach ($models as $model) {
    if (! $model->isFile() || $model->getExtension() !== 'php') {
        continue;
    }

    $source = file_get_contents($model->getPathname());
    if ($source === false) {
        $failures[] = 'Unreadable model: '.$model->getPathname();

        continue;
    }

    preg_match_all("/'([^']+)'\\s*=>\\s*'encrypted:array'/", $source, $matches);
    $relative = 'app/Models/'.basename($model->getPathname());
    foreach ($matches[1] as $field) {
        $discovered[] = $relative.':'.$field;
    }
}

sort($discovered);
$registered = array_keys($contracts);
sort($registered);

foreach (array_diff($discovered, $registered) as $missing) {
    $failures[] = 'Unregistered encrypted array cast: '.$missing;
}
foreach (array_diff($registered, $discovered) as $stale) {
    $failures[] = 'Stale encrypted storage contract: '.$stale;
}

foreach ($contracts as $key => $contract) {
    $migrationPath = $root.'/'.$contract['migration'];
    $migration = is_file($migrationPath) ? file_get_contents($migrationPath) : false;
    if ($migration === false) {
        $failures[] = 'Missing migration for '.$key.': '.$contract['migration'];

        continue;
    }

    $field = $contract['field'];
    $usesTextStorage = str_contains($migration, "\$table->text('{$field}')")
        || str_contains($migration, "\$table->longText('{$field}')");

    if (! $usesTextStorage) {
        $failures[] = 'Encrypted array storage must use text or longText: '.$key;
    }
    if (str_contains($migration, "\$table->json('{$field}')")) {
        $failures[] = 'Encrypted array storage must never use JSON: '.$key;
    }
}

$report = [
    'passed' => $failures === [],
    'discovered' => $discovered,
    'registered' => $registered,
    'failures' => $failures,
];
file_put_contents(
    $root.'/encrypted-storage-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
);

if ($failures !== []) {
    fwrite(STDERR, "Encrypted storage contract audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }

    exit(1);
}

fwrite(STDOUT, sprintf(
    "Encrypted storage contract audit passed for %d encrypted array fields.\n",
    count($contracts),
));
