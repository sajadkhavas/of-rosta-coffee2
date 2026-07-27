<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'hub schema' => [
        'file' => 'database/migrations/2026_07_27_120001_create_r5g_rosta_hub_schema.php',
        'needles' => [
            "Schema::create('rosta_hubs'",
            "Schema::create('rosta_hub_service_zones'",
            "Schema::create('rosta_hub_grinding_profile'",
            "'provider_hub_id'",
            'quote_item_service_provider_unique',
            'order_item_service_provider_unique',
        ],
    ],
    'hub eligibility' => [
        'file' => 'app/Services/Checkout/RoasteryGrindingSelection.php',
        'needles' => [
            "'rosta_hub'",
            'hub_zone_ineligible',
            'hub_capacity_unavailable',
            'unsupported_grind_profile',
            "'version' => 'r5g-rosta-hub-grinding-v1'",
            "'requires_address_confirmation'",
        ],
    ],
    'quote routing' => [
        'file' => 'app/Services/Checkout/QuoteService.php',
        'needles' => [
            'hub_address_confirmation_required',
            'r5g-rosta-hub-route-v1',
            'بسته‌بندی روستری در مسیر هاب: رایگان',
            'بسته‌بندی هاب رستا رایگان',
            "'provider_hub_id' => \$grinding['provider_hub_id']",
        ],
    ],
    'order route and ownership' => [
        'file' => 'app/Services/Checkout/OrderService.php',
        'needles' => [
            'roastery_to_rosta_hub',
            'rosta_hub_to_customer',
            "'owner_type' => 'rosta'",
            "type: 'hub.route_planned'",
            "'provider_hub_id' => \$quoteService->provider_hub_id",
        ],
    ],
    'feature acceptance' => [
        'file' => 'tests/Feature/R5GRostaHubGrindingTest.php',
        'needles' => [
            'test_ineligible_roastery_routes_grinding_through_hub_with_free_packaging_and_rosta_allocations',
            'test_hub_grinding_is_rejected_outside_enabled_tehran_or_karaj_zone',
            'test_available_roastery_remains_authoritative_and_hub_is_not_used',
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
    'hub_variant',
    'hub_sku',
    'hub_stock',
    'grinding_inventory',
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
    fwrite(STDERR, "R5G Hub audit failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "ROSTA_R5G_HUB_GRINDING_COMPLETE\n";
