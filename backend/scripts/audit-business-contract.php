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
    'routes/api.php',
    "'rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator'",
    'Seller routes must require an authorized operational role.',
);
$requireContains(
    'routes/api.php',
    "'rosta.role:administrator'",
    'Administration routes must require the administrator role.',
);

if ($failures !== []) {
    fwrite(STDERR, "Backend business contract audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

fwrite(STDOUT, "Backend business contract audit passed.\n");
