<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'enum' => $root.'/app/Enums/PackagingFeeMode.php',
    'migration' => $root.'/database/migrations/2026_07_26_110001_add_packaging_policy_to_products.php',
    'model' => $root.'/app/Models/Product.php',
    'policy' => $root.'/app/Services/Catalog/ProductPackagingPolicy.php',
    'request' => $root.'/app/Http/Requests/Catalog/UpsertProductRequest.php',
    'seller' => $root.'/app/Http/Controllers/Seller/SellerProductController.php',
    'quote' => $root.'/app/Services/Checkout/QuoteService.php',
    'order' => $root.'/app/Services/Checkout/OrderService.php',
    'quote_resource' => $root.'/app/Http/Resources/CheckoutQuoteResource.php',
    'order_resource' => $root.'/app/Http/Resources/OrderResource.php',
    'test' => $root.'/tests/Feature/R5DProductPackagingTest.php',
    'composer' => $root.'/composer.json',
    'contract' => dirname($root).'/docs/r5/R5D_PRODUCT_PACKAGING.md',
];

$sources = [];
foreach ($files as $key => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "R5D audit missing {$path}\n");
        exit(1);
    }

    $sources[$key] = file_get_contents($path);
}

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};
$hasAll = static function (string $source, array $fragments): bool {
    foreach ($fragments as $fragment) {
        if (! str_contains($source, $fragment)) {
            return false;
        }
    }

    return true;
};

$composer = json_decode($sources['composer'], true, flags: JSON_THROW_ON_ERROR);
$scripts = $composer['scripts'] ?? [];

$gate(
    'permanent_backend_gate',
    ($scripts['audit:r5d'] ?? null) === '@php scripts/audit-r5d-product-packaging.php'
        && in_array('@audit:r5d', $scripts['check'] ?? [], true),
    'composer check must permanently execute the R5D audit.',
);

$gate(
    'product_policy_is_authoritative',
    $hasAll($sources['enum'], ["case Free = 'free'", "case Fixed = 'fixed'"])
        && $hasAll($sources['migration'], ['packaging_fee_mode', 'packaging_fee_amount'])
        && $hasAll($sources['model'], ['PackagingFeeMode::class', 'public function packagingFee(): int'])
        && $hasAll($sources['policy'], [
            'catalog.packaging_amount_required',
            '$data[\'packaging_fee_amount\'] = $amount',
        ]),
    'Product packaging must be normalized by Laravel as free or positive fixed money.',
);

$gate(
    'seller_can_control_policy',
    $hasAll($sources['request'], ['packaging_fee_mode', 'packaging_fee_amount', 'PackagingFeeMode::class'])
        && $hasAll($sources['seller'], ['ProductPackagingPolicy', '->normalize(']),
    'Owner and manager product writes must pass through the packaging policy service.',
);

$gate(
    'explicit_quote_snapshot',
    $hasAll($sources['quote'], [
        "'service_type' => 'packaging'",
        'CheckoutQuoteItemService::query()->create',
        "'provider_type' => 'roastery'",
        "'packaging_total' => \$group['packaging_total']",
    ]) && $hasAll($sources['quote_resource'], ["'packaging_fee'", "'is_free'", "'packaging_total'"]),
    'Every quote item must expose an immutable packaging service, including free lines.',
);

$gate(
    'order_service_and_paid_ledger',
    $hasAll($sources['order'], [
        'OrderItemService::query()->create',
        "'allocation_type' => 'packaging'",
        'if ($orderService->packaging_fee > 0)',
    ]) && $hasAll($sources['order_resource'], ["'packaging_fee'", "'is_free'", "'packaging_total'"]),
    'Orders must retain packaging services while only non-zero packaging enters the payable ledger.',
);

$gate(
    'per_package_calculation',
    (
        $hasAll($sources['quote'], [
            '$unitPackagingFee = $product->packagingFee()',
            '$linePackagingTotal = $this->multiplyMoney($unitPackagingFee, $quantity)',
        ])
        || $hasAll($sources['quote'], [
            '$unitPackagingFee = $isHubGrinding ? 0 : $product->packagingFee()',
            '$linePackagingTotal = $this->multiplyMoney($unitPackagingFee, $quantity)',
            'r5g-rosta-hub-packaging-override-v1',
        ])
    ) && $hasAll($sources['test'], [
        "'quantity' => 3",
        '$quote->groups->sum(\'packaging_total\')',
        "'gross_amount' => 375_000",
    ]),
    'Fixed packaging is charged per purchased package and tested through quote, order and settlement.',
);

$gate(
    'free_is_visible_not_hidden',
    $hasAll($sources['policy'], ["'is_free' => \$amount === 0", 'بسته‌بندی روستری رایگان'])
        && $hasAll($sources['test'], [
            'snapshot($freeProduct)',
            '$freeService?->service_snapshot[\'label\']',
        ]),
    'A zero packaging fee must remain an explicit customer-visible fact.',
);

$gate(
    'r5d_scope_boundary',
    $hasAll($sources['contract'], [
        'Rosta Hub packaging remains outside R5D',
        'ROSTA_R5D_PRODUCT_PACKAGING_COMPLETE',
    ]),
    'R5D must stop at roastery product packaging; Hub grinding packaging begins in its dedicated phase.',
);

$failed = array_values(array_filter(
    $gates,
    static fn (array $item): bool => ! $item['passed'],
));
$report = [
    'generated_at' => (new DateTimeImmutable)->format(DATE_ATOM),
    'passed' => $failed === [],
    'checked_files' => array_values($files),
    'gates' => $gates,
    'failures' => array_column($failed, 'name'),
    'marker' => $failed === [] ? 'ROSTA_R5D_PRODUCT_PACKAGING_COMPLETE' : null,
];

file_put_contents(
    $root.'/r5d-product-packaging-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
);

if ($failed !== []) {
    fwrite(STDERR, "R5D product packaging audit failed:\n");
    foreach ($failed as $failure) {
        fwrite(STDERR, "- {$failure['name']}: {$failure['evidence']}\n");
    }
    exit(1);
}

fwrite(STDOUT, 'R5D product packaging audit passed ('.count($gates)." gates).\n");
fwrite(STDOUT, "ROSTA_R5D_PRODUCT_PACKAGING_COMPLETE\n");
