<?php

$root = dirname(__DIR__);
$failures = [];

$contracts = [
    'config/rosta.php' => [
        "'single_roastery_orders' => false",
        "'contract_version'",
    ],
    'app/Services/Checkout/QuoteService.php' => [
        'CheckoutQuoteGroup::query()->create',
        "'group_id' => \$quoteGroup->id",
        'resolveMarketplace(',
        'allocateMoney(',
        'ksort($resolvedGroups)',
    ],
    'app/Services/Checkout/CouponService.php' => [
        'resolveMarketplace(',
        'checkout.coupon_scope_invalid',
    ],
    'app/Services/Checkout/OrderService.php' => [
        'foreach ($quote->groups as $group)',
        'SubOrderAcceptanceStatus::AwaitingRoasteryAcceptance',
        'InventoryReservation::query()->create',
        'SettlementAllocation::query()->create',
        'ShipmentLeg::query()->create',
        'OrderEvent::query()->create',
        'assertQuoteTotals(',
        "'roastery_count' => \$quote->groups->count()",
    ],
    'app/Http/Resources/CheckoutQuoteResource.php' => [
        "'groups' => \$groups",
        "'discount_total' => \$group->discount_total",
        "'grand_total' => \$group->grand_total",
    ],
    'app/Http/Resources/OrderResource.php' => [
        "'sub_orders' => \$this->subOrders",
        "'acceptance_status'",
        "'customer_cancellable'",
        "'shipment_legs'",
        "'events'",
    ],
    'tests/Feature/R5CMultiRoasteryCheckoutTest.php' => [
        'assertJsonCount(2, \'data.groups\')',
        'assertDatabaseCount(\'sub_orders\', 2)',
        'assertDatabaseCount(\'settlement_allocations\', 4)',
        'assertDatabaseCount(\'shipment_legs\', 2)',
    ],
];

foreach ($contracts as $relativePath => $needles) {
    $path = $root.'/'.$relativePath;
    $content = is_file($path) ? file_get_contents($path) : false;

    if ($content === false) {
        $failures[] = 'Missing R5C file: '.$relativePath;
        continue;
    }

    foreach ($needles as $needle) {
        if (! str_contains($content, $needle)) {
            $failures[] = 'Missing R5C contract in '.$relativePath.': '.$needle;
        }
    }
}

$quoteService = file_get_contents($root.'/app/Services/Checkout/QuoteService.php');
if ($quoteService !== false && str_contains($quoteService, "throw new ApiDomainException(\n                    'cart.multiple_roasteries'")) {
    $failures[] = 'R5C must not reject a cart merely because it contains multiple roasteries.';
}

$orderService = file_get_contents($root.'/app/Services/Checkout/OrderService.php');
if ($orderService !== false && str_contains($orderService, "'roastery_id' => \$quote->roastery_id")) {
    $failures[] = 'Parent marketplace orders must not inherit a single quote roastery unconditionally.';
}

$report = [
    'passed' => $failures === [],
    'marker' => 'ROSTA_R5C_MARKETPLACE_CHECKOUT_COMPLETE',
    'failures' => $failures,
];
file_put_contents(
    $root.'/r5c-marketplace-checkout-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
);

if ($failures !== []) {
    fwrite(STDERR, "R5C marketplace checkout audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }

    exit(1);
}

fwrite(STDOUT, "ROSTA_R5C_MARKETPLACE_CHECKOUT_COMPLETE\n");
