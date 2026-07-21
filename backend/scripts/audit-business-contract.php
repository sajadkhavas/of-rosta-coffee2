<?php

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relativePath) use ($root, &$failures): string {
    $path = $root.'/'.$relativePath;
    $content = is_file($path) ? file_get_contents($path) : false;

    if ($content === false) {
        $failures[] = 'Missing or unreadable file: '.$relativePath;

        return '';
    }

    return $content;
};

$requireContains = static function (
    string $relativePath,
    string $needle,
    string $message,
) use ($read, &$failures): void {
    if (! str_contains($read($relativePath), $needle)) {
        $failures[] = $message;
    }
};

foreach (['app', 'config', 'database', 'routes'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'json', 'yml', 'yaml'], true)) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            $failures[] = 'Unreadable file: '.$file->getPathname();
            continue;
        }

        if (preg_match('/\bgrind(?:Type|_type|Setting|_setting)?\b/i', $content) === 1) {
            $failures[] = 'Forbidden grind state: '.$file->getPathname();
        }
    }
}

$config = $read('config/rosta.php');
foreach (['50', '100', '250', '500', '1000'] as $weight) {
    if (! str_contains($config, $weight)) {
        $failures[] = 'Missing whole-bean weight '.$weight.'.';
    }
}
foreach ([
    "'single_roastery_orders' => true" => 'Single-roastery order boundary is not locked.',
    "'allowed_media_hosts'" => 'Media host allowlist is missing.',
    "'quote_ttl_minutes'" => 'Quote TTL is missing.',
    "'reservation_ttl_minutes'" => 'Reservation TTL is missing.',
    "'idempotency_ttl_hours'" => 'Order idempotency TTL is missing.',
] as $needle => $message) {
    if (! str_contains($config, $needle)) {
        $failures[] = $message;
    }
}

$contractPath = dirname($root).'/docs/openapi/rosta-v1-phase6.yaml';
if (! is_file($contractPath) || filesize($contractPath) === 0) {
    $failures[] = 'Frozen OpenAPI contract is missing.';
}

$checks = [
    'app/Services/Identity/OtpCodeHasher.php' => [
        'random_int(' => 'OTP codes must use a secure generator.',
        'hash_hmac(' => 'OTP codes must use keyed digests.',
        "'sha256'" => 'OTP digests must use SHA-256.',
    ],
    'app/Services/Identity/OtpService.php' => [
        'Cache::lock(' => 'OTP transitions must use distributed locks.',
        'lockForUpdate()' => 'OTP transitions must use row locks.',
    ],
    'app/Jobs/SendOtpCode.php' => [
        'Crypt::decryptString(' => 'Queued OTP plaintext must remain encrypted.',
    ],
    'app/Http/Middleware/EnsureActiveAuthSession.php' => [
        'isActive()' => 'Recorded sessions must be checked for expiry and revocation.',
    ],
    'app/Services/Identity/AddressService.php' => [
        "->where('user_id', \$user->id)" => 'Addresses must be owner scoped.',
    ],
    'app/Models/AuditLog.php' => [
        'static::updating' => 'Audit records must reject updates.',
        'static::deleting' => 'Audit records must reject deletes.',
    ],
    'app/Services/Catalog/CatalogAccess.php' => [
        "->where('scope_type', 'roastery')" => 'Seller access must be roastery scoped.',
        "->where('scope_id', \$roastery->id)" => 'Seller access must match the roastery id.',
        'ModelNotFoundException' => 'Foreign seller resources must be concealed.',
    ],
    'app/Services/Catalog/StockService.php' => [
        'lockForUpdate()' => 'Stock adjustments must lock variants.',
        'StockLedgerEntry::query()->create' => 'Stock adjustments must append ledger entries.',
        'inventory.idempotency_conflict' => 'Stock idempotency must be payload-bound.',
        'inventory.reserved_stock_conflict' => 'Stock adjustments must preserve reservations.',
    ],
    'app/Models/StockLedgerEntry.php' => [
        'static::updating' => 'Stock ledger entries must reject updates.',
        'static::deleting' => 'Stock ledger entries must reject deletes.',
    ],
    'app/Models/Product.php' => [
        "->where('status', ProductStatus::Published->value)" => 'Public products must be published.',
        "->where('status', 'verified')" => 'Public products require a verified roastery.',
        "->where('is_active', true)" => 'Public products require an active variant.',
    ],
    'app/Services/Catalog/CatalogPublicationService.php' => [
        'isPubliclyVisible()' => 'Publishing must reject unverified roasteries.',
    ],
    'app/Http/Requests/Catalog/UpsertVariantRequest.php' => [
        "Rule::in(config('rosta.whole_bean_weights'))" => 'Variant weights must use the permanent list.',
    ],
    'app/Services/Catalog/MediaRegistrationService.php' => [
        "config('rosta.catalog.allowed_media_hosts'" => 'Media must use a server allowlist.',
        "in_array(\$host, \$allowedHosts, true)" => 'Media hosts must be allowlisted.',
    ],
    'app/Services/Checkout/QuoteService.php' => [
        'cart.multiple_roasteries' => 'Cart validation must reject multiple roasteries.',
        'availableQuantity()' => 'Quotes must use authoritative stock.',
        "'unit_price' => \$variant->price" => 'Quotes must use authoritative prices.',
        'private const int MAX_MONEY' => 'Money calculations need a safe upper bound.',
        'multiplyMoney(' => 'Line totals must use checked multiplication.',
        'addMoney(' => 'Quote totals must use checked addition.',
        "'product_snapshot'" => 'Product snapshots are required.',
        "'variant_snapshot'" => 'Variant snapshots are required.',
        "'roast_batch_snapshot'" => 'Roast-batch snapshots are required.',
    ],
    'app/Services/Checkout/CouponService.php' => [
        '$subtotal % 10_000' => 'Percentage discounts must avoid integer overflow.',
    ],
    'app/Services/Checkout/OrderService.php' => [
        'User::query()->lockForUpdate()' => 'Order creation must serialize per customer.',
        'order.idempotency_conflict' => 'Order idempotency must be payload-bound.',
        "'stock_reserved' => \$variant->stock_reserved + \$quoteItem->quantity" => 'Order creation must reserve stock.',
        'InventoryReservation::query()->create' => 'Explicit reservations are required.',
        "'consumed_at' => now()" => 'Quotes must be consumed once.',
        "config('rosta.checkout.idempotency_ttl_hours'" => 'Order idempotency must use configured retention.',
    ],
    'app/Services/Checkout/OrderCancellationService.php' => [
        'OrderStatus::AwaitingPayment' => 'Direct cancellation must have a bounded state.',
        'stock_reserved - $reservation->quantity' => 'Cancellation must release stock.',
        'releaseCouponReservation(' => 'Cancellation must release coupon capacity.',
    ],
    'app/Services/Checkout/ReservationExpiryService.php' => [
        'ReservationStatus::Active->value' => 'Expiry must target active reservations.',
    ],
    'routes/console.php' => [
        'rosta.checkout.expire-reservations' => 'Reservation expiry must be scheduled.',
        'withoutOverlapping()' => 'Recovery jobs must not overlap.',
    ],
];

foreach ($checks as $file => $needles) {
    foreach ($needles as $needle => $message) {
        $requireContains($file, $needle, $message);
    }
}

$identityMigration = $read('database/migrations/2026_07_21_010001_create_identity_access_tables.php');
if (! str_contains($identityMigration, 'code_digest')) {
    $failures[] = 'OTP challenge storage must contain code_digest.';
}
if (preg_match('~otp_challenges[\s\S]*?\$table->(?:string|char)\(\'code\'~', $identityMigration) === 1) {
    $failures[] = 'Plaintext OTP columns are forbidden.';
}

$checkoutMigration = 'database/migrations/2026_07_21_030001_create_transactional_checkout_tables.php';
foreach ([
    "Schema::create('checkout_quotes'",
    "Schema::create('orders'",
    "Schema::create('sub_orders'",
    "Schema::create('inventory_reservations'",
    "Schema::create('order_idempotency_keys'",
] as $table) {
    $requireContains($checkoutMigration, $table, 'Missing checkout table: '.$table);
}
$requireContains(
    $checkoutMigration,
    "foreignUlid('order_id')->unique()",
    'Single-roastery orders must have one sub-order.',
);
$requireContains(
    $checkoutMigration,
    "unique(['user_id', 'key'])",
    'Idempotency keys must be customer scoped.',
);

$routes = $read('routes/api.php');
foreach ([
    '/seller/origins',
    '/media',
    'roast-batches',
    'stock-ledger',
    '/admin/origins',
    '/cart/validate',
    '/checkout/quote',
    '/orders',
    '/orders/{orderId}/cancel',
    'throttle:cart-validate',
    'throttle:checkout-quote',
    'throttle:order-create',
] as $routeBoundary) {
    if (! str_contains($routes, $routeBoundary)) {
        $failures[] = 'Missing route boundary: '.$routeBoundary;
    }
}
foreach ([
    "['auth:sanctum', 'rosta.session']",
    'rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator',
    'rosta.role:administrator',
] as $middlewareBoundary) {
    if (! str_contains($routes, $middlewareBoundary)) {
        $failures[] = 'Missing middleware boundary: '.$middlewareBoundary;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Backend business contract audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }

    exit(1);
}

fwrite(STDOUT, "Backend business contract audit passed.\n");
