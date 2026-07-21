<?php

$root = dirname(__DIR__);
$scanRoots = [
    $root.'/app',
    $root.'/config',
    $root.'/database',
    $root.'/routes',
];
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

foreach ($scanRoots as $scanRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
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

$rostaConfig = $read('config/rosta.php');
foreach (['50', '100', '250', '500', '1000'] as $weight) {
    if (! str_contains($rostaConfig, $weight)) {
        $failures[] = 'Missing whole-bean weight '.$weight.' in config/rosta.php';
    }
}
if (! str_contains($rostaConfig, "'single_roastery_orders' => true")) {
    $failures[] = 'Single-roastery order boundary is not locked.';
}
if (! str_contains($rostaConfig, "'allowed_media_hosts'")) {
    $failures[] = 'Server-owned media host allowlist is not configured.';
}
foreach (['quote_ttl_minutes', 'reservation_ttl_minutes', 'idempotency_ttl_hours'] as $checkoutSetting) {
    if (! str_contains($rostaConfig, "'{$checkoutSetting}'")) {
        $failures[] = 'Missing checkout safety setting: '.$checkoutSetting;
    }
}

$contractPath = dirname($root).'/docs/openapi/rosta-v1-phase6.yaml';
if (! is_file($contractPath) || filesize($contractPath) === 0) {
    $failures[] = 'Frozen OpenAPI contract is missing.';
}

$requireContains(
    'app/Services/Identity/OtpCodeHasher.php',
    'random_int(',
    'OTP codes must use a cryptographically secure generator.',
);
$requireContains(
    'app/Services/Identity/OtpCodeHasher.php',
    'hash_hmac(',
    'OTP codes must be stored with a keyed digest.',
);
$requireContains(
    'app/Services/Identity/OtpCodeHasher.php',
    "'sha256'",
    'OTP digests must use SHA-256.',
);

$otpMigration = $read('database/migrations/2026_07_21_010001_create_identity_access_tables.php');
if (! str_contains($otpMigration, 'code_digest')) {
    $failures[] = 'OTP challenge storage must contain code_digest.';
}
if (preg_match(
    '~otp_challenges[\s\S]*?\$table->(?:string|char)\(\'code\'~',
    $otpMigration,
) === 1) {
    $failures[] = 'Plaintext OTP code columns are forbidden.';
}

$requireContains(
    'app/Services/Identity/OtpService.php',
    'Cache::lock(',
    'OTP request and verification must use distributed locks.',
);
$requireContains(
    'app/Services/Identity/OtpService.php',
    'lockForUpdate()',
    'OTP state transitions must use database row locks.',
);
$requireContains(
    'app/Jobs/SendOtpCode.php',
    'Crypt::decryptString(',
    'Queued OTP plaintext must remain encrypted in the job payload.',
);
$requireContains(
    'routes/api.php',
    "['auth:sanctum', 'rosta.session']",
    'Private routes must require Sanctum and an active recorded session.',
);
$requireContains(
    'app/Http/Middleware/EnsureActiveAuthSession.php',
    'isActive()',
    'Recorded sessions must be checked for revocation and expiry.',
);
$requireContains(
    'app/Services/Identity/AddressService.php',
    "->where('user_id', \$user->id)",
    'Address access must always be scoped to the authenticated owner.',
);
$requireContains(
    'app/Models/AuditLog.php',
    'static::updating',
    'Audit records must reject updates.',
);
$requireContains(
    'app/Models/AuditLog.php',
    'static::deleting',
    'Audit records must reject deletes.',
);

$requireContains(
    'app/Services/Catalog/CatalogAccess.php',
    "->where('scope_type', 'roastery')",
    'Seller catalog access must be scoped to a roastery assignment.',
);
$requireContains(
    'app/Services/Catalog/CatalogAccess.php',
    "->where('scope_id', \$roastery->id)",
    'Seller catalog access must match the requested roastery id.',
);
$requireContains(
    'app/Services/Catalog/CatalogAccess.php',
    'ModelNotFoundException',
    'Foreign seller resources must be concealed as not found.',
);
$requireContains(
    'app/Services/Catalog/StockService.php',
    'lockForUpdate()',
    'Stock adjustments must lock the authoritative variant row.',
);
$requireContains(
    'app/Services/Catalog/StockService.php',
    'StockLedgerEntry::query()->create',
    'Every stock adjustment must append a ledger entry.',
);
$requireContains(
    'app/Services/Catalog/StockService.php',
    'inventory.idempotency_conflict',
    'Stock idempotency keys must be bound to their original payload.',
);
$requireContains(
    'app/Services/Catalog/StockService.php',
    'inventory.reserved_stock_conflict',
    'Stock adjustments must preserve active reservations.',
);
$requireContains(
    'app/Models/StockLedgerEntry.php',
    'static::updating',
    'Stock ledger entries must reject updates.',
);
$requireContains(
    'app/Models/StockLedgerEntry.php',
    'static::deleting',
    'Stock ledger entries must reject deletes.',
);
$requireContains(
    'app/Models/Product.php',
    "->where('status', ProductStatus::Published->value)",
    'Public product scope must require published status.',
);
$requireContains(
    'app/Models/Product.php',
    "->where('status', 'verified')",
    'Public product scope must require a verified roastery.',
);
$requireContains(
    'app/Models/Product.php',
    "->where('is_active', true)",
    'Public product scope must require an active whole-bean variant.',
);
$requireContains(
    'app/Services/Catalog/CatalogPublicationService.php',
    'isPubliclyVisible()',
    'Publishing must reject products from unverified roasteries.',
);
$requireContains(
    'app/Http/Requests/Catalog/UpsertVariantRequest.php',
    "Rule::in(config('rosta.whole_bean_weights'))",
    'Variant weight must be limited to the permanent whole-bean list.',
);
$requireContains(
    'app/Http/Requests/Catalog/AdjustStockRequest.php',
    'StockReason::Return->value',
    'Seller stock reasons must be explicitly allowlisted.',
);
$requireContains(
    'app/Services/Catalog/MediaRegistrationService.php',
    "config('rosta.catalog.allowed_media_hosts'",
    'Media registration must use the server-owned host allowlist.',
);
$requireContains(
    'app/Services/Catalog/MediaRegistrationService.php',
    "in_array(\$host, \$allowedHosts, true)",
    'Media URLs must belong to an allowlisted storage host.',
);
$requireContains(
    'app/Http/Controllers/Seller/SellerProductController.php',
    'returned_to_review',
    'Seller product edits must return published content to moderation.',
);
$requireContains(
    'app/Http/Controllers/Seller/SellerRoasteryController.php',
    'verification_reset',
    'Seller identity edits must reset roastery verification.',
);
$requireContains(
    'routes/api.php',
    "'rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator'",
    'Seller routes must require an authorized operational role.',
);
$requireContains(
    'routes/api.php',
    "'rosta.role:administrator'",
    'Administration routes must require the administrator role.',
);
foreach ([
    "'/seller/origins'",
    "'/media'",
    "'/roast-batches'",
    "'/stock-ledger'",
    "'/admin/origins'",
] as $requiredRoute) {
    if (! str_contains($read('routes/api.php'), $requiredRoute)) {
        $failures[] = 'Missing catalog operations route: '.$requiredRoute;
    }
}

$checkoutMigration = 'database/migrations/2026_07_21_030001_create_transactional_checkout_tables.php';
foreach ([
    "Schema::create('checkout_quotes'",
    "Schema::create('checkout_quote_items'",
    "Schema::create('orders'",
    "Schema::create('sub_orders'",
    "Schema::create('order_items'",
    "Schema::create('inventory_reservations'",
    "Schema::create('order_idempotency_keys'",
] as $requiredTable) {
    $requireContains(
        $checkoutMigration,
        $requiredTable,
        'Missing transactional checkout table: '.$requiredTable,
    );
}
$requireContains(
    $checkoutMigration,
    "foreignUlid('order_id')->unique()",
    'Every order must map to exactly one sub-order in the single-roastery model.',
);
$requireContains(
    $checkoutMigration,
    "unique(['user_id', 'key'])",
    'Order idempotency keys must be customer scoped.',
);
$requireContains(
    $checkoutMigration,
    "unique(['quote_id', 'variant_id'])",
    'Quote variants must be unique.',
);

$requireContains(
    'app/Services/Checkout/QuoteService.php',
    'cart.multiple_roasteries',
    'Cart validation must reject multiple roasteries.',
);
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    'availableQuantity()',
    'Quote creation must use authoritative available stock.',
);
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    "'unit_price' => \$variant->price",
    'Quote prices must come from the authoritative variant.',
);
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    "'product_snapshot'",
    'Quote items must persist immutable product snapshots.',
);
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    "'variant_snapshot'",
    'Quote items must persist immutable variant snapshots.',
);
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    "'roast_batch_snapshot'",
    'Quote items must persist immutable roast-batch snapshots.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    'User::query()->lockForUpdate()',
    'Order creation must serialize requests per customer.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    "->where('user_id', \$user->id)",
    'Order idempotency and ownership must be customer scoped.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    'order.idempotency_conflict',
    'Order idempotency keys must be payload-bound.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    "->lockForUpdate()",
    'Order creation must lock quote, variants and mutable counters.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    "'stock_reserved' => \$variant->stock_reserved + \$quoteItem->quantity",
    'Order creation must reserve stock atomically.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    'InventoryReservation::query()->create',
    'Every reserved order line must have an explicit reservation.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    "'consumed_at' => now()",
    'A checkout quote must be consumed exactly once.',
);
$requireContains(
    'app/Services/Checkout/OrderCancellationService.php',
    'OrderStatus::AwaitingPayment',
    'Direct customer cancellation must have a bounded state transition.',
);
$requireContains(
    'app/Services/Checkout/OrderCancellationService.php',
    'stock_reserved - $reservation->quantity',
    'Cancellation and expiry must release reserved stock.',
);
$requireContains(
    'app/Services/Checkout/ReservationExpiryService.php',
    "ReservationStatus::Active->value",
    'Reservation expiry must target active reservations only.',
);
$requireContains(
    'routes/console.php',
    'rosta.checkout.expire-reservations',
    'Reservation expiry must be scheduled.',
);
$requireContains(
    'routes/console.php',
    'withoutOverlapping()',
    'Checkout recovery jobs must not overlap.',
);
foreach ([
    "'/cart/validate'",
    "'/checkout/quote'",
    "'/orders'",
    "'/orders/{orderId}/cancel'",
    "'/seller/roasteries/{roasteryId}'",
    "'/admin'",
] as $requiredRoute) {
    if (! str_contains($read('routes/api.php'), $requiredRoute)) {
        $failures[] = 'Missing transactional checkout route boundary: '.$requiredRoute;
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
