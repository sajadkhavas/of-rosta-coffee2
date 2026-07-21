<?php

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use ($root, &$failures): string {
    $content = is_file($root.'/'.$path) ? file_get_contents($root.'/'.$path) : false;
    if ($content === false) {
        $failures[] = 'Missing payment file: '.$path;

        return '';
    }

    return $content;
};

$require = static function (string $path, string $needle, string $message) use ($read, &$failures): void {
    if (! str_contains($read($path), $needle)) {
        $failures[] = $message;
    }
};

$migration = 'database/migrations/2026_07_21_040001_create_payments_ledger_tables.php';
foreach ([
    "Schema::create('payment_attempts'",
    "Schema::create('payment_events'",
    "Schema::create('ledger_transactions'",
    "Schema::create('ledger_entries'",
    "Schema::create('payment_refunds'",
    "Schema::create('settlement_batches'",
    "Schema::create('payment_reconciliations'",
] as $table) {
    $require($migration, $table, 'Missing payment schema: '.$table);
}

$checks = [
    'app/Providers/AppServiceProvider.php' => [
        'PaymentProvider::class' => 'Payment provider must be container-bound.',
        'DisabledPaymentProvider' => 'Production payment must default to disabled.',
        "RateLimiter::for('payment-callback'" => 'Payment callback needs a dedicated rate limit.',
    ],
    'app/Services/Payments/PaymentAttemptService.php' => [
        "->where('user_id', \$user->id)" => 'Payment attempts must be customer scoped.',
        'payment.idempotency_conflict' => 'Payment requests must be payload-bound.',
        'assertSafeRedirect(' => 'Provider redirects must be allowlisted.',
        "'amount' => \$order->grand_total" => 'Payment amount must come from the order.',
    ],
    'app/Services/Payments/PaymentCallbackService.php' => [
        'parseCallback(' => 'Callbacks must be parsed and authenticated by the provider adapter.',
        'PaymentVerificationService' => 'Callback receipt alone must not prove payment.',
    ],
    'app/Services/Payments/PaymentVerificationService.php' => [
        'lockForUpdate()' => 'Paid transitions must use row locks.',
        'payment.verification_mismatch' => 'Amount and currency mismatch must be rejected.',
        'ReservationStatus::Active' => 'Paid transitions require active reservations.',
        "'stock_on_hand' => \$newOnHand" => 'Paid transitions must consume stock.',
        'StockReason::OrderConsumed' => 'Paid stock changes need an immutable reason.',
        'PaymentReconciliation::query()->create' => 'Ambiguous paid responses need reconciliation.',
        'recordSale(' => 'Paid transitions must create financial ledger records.',
    ],
    'app/Services/Payments/LedgerService.php' => [
        '$debits !== $credits' => 'Ledger transactions must be balanced.',
        'LedgerAccount::PlatformRevenue' => 'Platform commission must be explicit.',
        'LedgerAccount::SellerPayable' => 'Seller payable must be explicit.',
    ],
    'app/Models/PaymentEvent.php' => [
        'static::updating' => 'Payment events must be append-only.',
        'static::deleting' => 'Payment events must be append-only.',
    ],
    'app/Models/LedgerEntry.php' => [
        'static::updating' => 'Ledger entries must be append-only.',
        'static::deleting' => 'Ledger entries must be append-only.',
    ],
    'app/Services/Payments/PaymentRefundService.php' => [
        'refund.idempotency_conflict' => 'Refunds must be idempotent.',
        'recordRefund(' => 'Refunds must reverse the financial ledger.',
    ],
    'routes/console.php' => [
        'rosta.payments.reconcile' => 'Payment reconciliation must be scheduled.',
        'withoutOverlapping()' => 'Payment reconciliation must not overlap.',
    ],
];

foreach ($checks as $file => $needles) {
    foreach ($needles as $needle => $message) {
        $require($file, $needle, $message);
    }
}

$routes = $read('routes/api.php');
foreach ([
    '/payments/request',
    '/payments/callback',
    '/payments/{paymentId}/verify',
    '/finance/summary',
    '/finance/ledger',
    '/finance/settlements',
    '/payments/{paymentId}/refund',
    '/finance/reconciliations',
] as $route) {
    if (! str_contains($routes, $route)) {
        $failures[] = 'Missing payment route: '.$route;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Payment contract audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }

    exit(1);
}

fwrite(STDOUT, "Payment contract audit passed.\n");
