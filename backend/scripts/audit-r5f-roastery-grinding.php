<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'selection policy' => [
        'file' => 'app/Services/Checkout/RoasteryGrindingSelection.php',
        'needles' => [
            'checkout.grinding_profile_unsupported',
            'checkout.grinding_weight_unsupported',
            'checkout.grinding_capacity_unavailable',
            "'version' => 'r5f-roastery-grinding-v1'",
            "'provider_type' => 'roastery'",
        ],
    ],
    'cart request' => [
        'file' => 'app/Http/Requests/Checkout/CartValidateRequest.php',
        'needles' => ["'grinding_profile_id'", 'items.*.grinding_profile_id'],
    ],
    'checkout request' => [
        'file' => 'app/Http/Requests/Checkout/CheckoutQuoteRequest.php',
        'needles' => ["'grinding_profile_id'", 'items.*.grinding_profile_id'],
    ],
    'quote pricing' => [
        'file' => 'app/Services/Checkout/QuoteService.php',
        'needles' => [
            'RoasteryGrindingSelection $grinding',
            "'grinding_total' => \$group['grinding_total']",
            "'service_type' => 'grinding'",
            "'grinding_profile_id' => \$grinding['profile']->id",
        ],
    ],
    'order snapshot and allocation' => [
        'file' => 'app/Services/Checkout/OrderService.php',
        'needles' => [
            'assertQuoteServiceOrderable',
            "'allocation_type' => 'grinding'",
            "type: 'grinding.requested'",
            'orderItemService: $orderService',
        ],
    ],
    'customer resources' => [
        'file' => 'app/Http/Resources/CheckoutQuoteResource.php',
        'needles' => ["'service_fee'", "'grinding_profile'", "'grinding_total'"],
    ],
    'feature acceptance' => [
        'file' => 'tests/Feature/R5FRoasteryGrindingSelectionTest.php',
        'needles' => [
            'test_roastery_grinding_is_snapshotted_priced_and_allocated_without_changing_inventory_identity',
            'checkout.grinding_profile_unsupported',
        ],
    ],
];

$failures = [];
foreach ($checks as $name => $check) {
    $path = $root.'/'.$check['file'];
    if (! is_file($path)) {
        $failures[] = $name.': missing '.$check['file'];

        continue;
    }
    $contents = file_get_contents($path);
    if (! is_string($contents)) {
        $failures[] = $name.': unreadable '.$check['file'];

        continue;
    }
    foreach ($check['needles'] as $needle) {
        if (! str_contains($contents, $needle)) {
            $failures[] = $name.': missing contract '.$needle;
        }
    }
}

$forbidden = [
    'product_variant_grinding',
    'grind_variant',
    'grinding_stock',
];
foreach (['app', 'database/migrations'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (! is_string($contents)) {
            continue;
        }
        foreach ($forbidden as $needle) {
            if (str_contains($contents, $needle)) {
                $failures[] = 'whole-bean boundary: forbidden '.$needle.' in '.$file->getPathname();
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "R5F grinding audit failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "ROSTA_R5F_ROASTERY_GRINDING_COMPLETE\n";
