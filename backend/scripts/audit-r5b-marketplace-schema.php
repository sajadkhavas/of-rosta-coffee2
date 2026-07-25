<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'migration' => $root.'/database/migrations/2026_07_25_230001_create_r5b_marketplace_schema.php',
    'order' => $root.'/app/Models/Order.php',
    'sub_order' => $root.'/app/Models/SubOrder.php',
    'quote' => $root.'/app/Models/CheckoutQuote.php',
    'quote_item' => $root.'/app/Models/CheckoutQuoteItem.php',
    'service' => $root.'/app/Models/OrderItemService.php',
    'event' => $root.'/app/Models/OrderEvent.php',
    'acceptance' => $root.'/app/Enums/SubOrderAcceptanceStatus.php',
    'test' => $root.'/tests/Feature/R5BMarketplaceSchemaTest.php',
    'composer' => $root.'/composer.json',
    'contract' => dirname($root).'/docs/r5/R5B_MARKETPLACE_SCHEMA.md',
];

$sources = [];
foreach ($files as $key => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "R5B audit missing {$path}\n");
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
    ($scripts['audit:r5b'] ?? null) === '@php scripts/audit-r5b-marketplace-schema.php'
        && in_array('@audit:r5b', $scripts['check'] ?? [], true),
    'composer check must permanently execute the R5B audit.',
);

$gate(
    'parent_order_multi_sub_order',
    $hasAll($sources['migration'], [
        "dropUnique('sub_orders_order_id_unique')",
        'sub_orders_order_status_index',
        'acceptance_status',
        'checkout_quote_groups',
    ]) && $hasAll($sources['order'], [
        'public function subOrders(): HasMany',
        'Legacy single-sub-order compatibility relation',
    ]),
    'Order must become the parent aggregate while legacy single-sub-order reads remain compatible.',
);

$gate(
    'legacy_upgrade_backfill',
    $hasAll($sources['migration'], [
        'backfillLegacySubOrders',
        'backfillLegacyQuoteGroups',
        'legacy_single_roastery_quote',
        'rejected_by_roastery',
        'cancelled_by_customer',
    ]),
    'Legacy one-roastery data must be upgraded rather than discarded or recreated.',
);

$gate(
    'customer_cancellation_boundary',
    $hasAll($sources['acceptance'], [
        'AwaitingRoasteryAcceptance',
        'customerCancellable',
    ]) && $hasAll($sources['sub_order'], [
        'SubOrderAcceptanceStatus',
        'isCustomerCancellable',
    ]) && $hasAll($sources['test'], [
        'assertTrue($pending->isCustomerCancellable())',
        'assertFalse($accepted->isCustomerCancellable())',
    ]),
    'Customer cancellation capability must derive only from acceptance state.',
);

$gate(
    'whole_bean_inventory_preserved',
    ! str_contains($sources['migration'], "Schema::table('product_variants'")
        && ! str_contains($sources['migration'], "Schema::table('roast_batches'")
        && $hasAll($sources['contract'], [
            'Whole-bean inventory remains unchanged',
            'order-item service',
        ]),
    'R5B must not add grinding state to product variants, roast batches or stock identity.',
);

$gate(
    'quote_and_service_snapshot_schema',
    $hasAll($sources['migration'], [
        'checkout_quote_item_services',
        'order_item_services',
        'pricing_snapshot',
        'service_snapshot',
        'packaging_fee',
        'grinding_profile_id',
    ]) && $hasAll($sources['quote'], [
        'public function groups(): HasMany',
    ]) && $hasAll($sources['quote_item'], [
        'public function services(): HasMany',
    ]),
    'Quote and order service choices must be immutable, grouped snapshots.',
);

$gate(
    'shipment_allocation_tax_event_schema',
    $hasAll($sources['migration'], [
        "Schema::create('shipment_legs'",
        "Schema::create('settlement_allocations'",
        "Schema::create('order_tax_lines'",
        "Schema::create('order_events'",
        'idempotency_key',
        'charge_owner_type',
    ]),
    'Shipment legs, settlement ownership, tax lines and tracking events must be child-scoped.',
);

$gate(
    'append_only_customer_timeline',
    $hasAll($sources['event'], [
        'Order events are append-only and cannot be updated.',
        'Order events are append-only and cannot be deleted.',
        "internal_metadata' => 'encrypted:array",
    ]) && $hasAll($sources['test'], [
        'test_order_events_are_append_only',
        'assertDatabaseHas',
    ]),
    'Customer tracking must be append-only and private metadata must remain encrypted.',
);

$gate(
    'rollback_is_fail_closed',
    $hasAll($sources['migration'], [
        'R5B rollback refused because multi-roastery orders already exist.',
        'R5B rollback refused because parent orders or quotes no longer have a legacy roastery.',
    ]),
    'Rollback must refuse destructive downgrade after real multi-roastery data exists.',
);

$gate(
    'r5b_scope_boundary',
    $hasAll($sources['contract'], [
        'No public checkout behaviour is activated in R5B',
        'R5C',
        'ROSTA_R5B_MARKETPLACE_SCHEMA_COMPLETE',
    ]),
    'R5B must stop at schema, models, compatibility and tests.',
);

$failed = array_values(array_filter(
    $gates,
    static fn (array $gate): bool => ! $gate['passed'],
));

$report = [
    'generated_at' => (new DateTimeImmutable)->format(DATE_ATOM),
    'passed' => $failed === [],
    'checked_files' => array_values($files),
    'gates' => $gates,
    'failures' => array_column($failed, 'name'),
    'marker' => $failed === [] ? 'ROSTA_R5B_MARKETPLACE_SCHEMA_COMPLETE' : null,
];

file_put_contents(
    $root.'/r5b-marketplace-schema-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
);

if ($failed !== []) {
    fwrite(STDERR, "R5B marketplace schema audit failed:\n");
    foreach ($failed as $failure) {
        fwrite(STDERR, "- {$failure['name']}: {$failure['evidence']}\n");
    }
    exit(1);
}

fwrite(STDOUT, 'R5B marketplace schema audit passed ('.count($gates)." gates).\n");
fwrite(STDOUT, "ROSTA_R5B_MARKETPLACE_SCHEMA_COMPLETE\n");
