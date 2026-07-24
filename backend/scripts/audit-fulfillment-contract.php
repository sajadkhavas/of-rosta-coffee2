<?php

$root = dirname(__DIR__);
$files = [
    'service' => file_get_contents($root.'/app/Services/Fulfillment/FulfillmentService.php'),
    'request' => file_get_contents($root.'/app/Http/Requests/Fulfillment/UpdateFulfillmentRequest.php'),
    'routes' => file_get_contents($root.'/routes/fulfillment.php'),
    'seller' => file_get_contents($root.'/app/Http/Controllers/Seller/SellerOrderController.php'),
    'admin' => file_get_contents($root.'/app/Http/Controllers/Admin/AdminOrderController.php'),
    'history' => file_get_contents($root.'/app/Models/SubOrderStatusHistory.php'),
    'note' => file_get_contents($root.'/app/Models/OrderInternalNote.php'),
    'restock' => file_get_contents($root.'/app/Models/InventoryRestock.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_07_22_200001_create_fulfillment_tables.php'),
    'resource' => file_get_contents($root.'/app/Http/Resources/OrderResource.php'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$gate(
    'roastery_scoped_writes',
    str_contains($files['routes'], '/seller/roasteries/{roasteryId}/orders/{orderId}/fulfillment')
        && str_contains($files['seller'], 'assertRoasteryAccess')
        && str_contains($files['service'], "->where('roastery_id', \$roastery->id)"),
    'Seller fulfillment writes must be roastery-scoped at route, access and query boundaries.',
);

$gate(
    'single_domain_state_machine',
    str_contains($files['seller'], 'FulfillmentService')
        && str_contains($files['admin'], 'FulfillmentService')
        && str_contains($files['service'], 'assertTransitionAllowed')
        && str_contains($files['service'], 'fulfillment.transition_invalid'),
    'Seller and administrator transitions must use one controlled domain service.',
);

$gate(
    'tracking_required_before_shipping',
    str_contains($files['request'], 'required_if:status,shipped')
        && str_contains($files['service'], 'fulfillment.tracking_required')
        && str_contains($files['service'], "'tracking_code' => \$trackingCode"),
    'Shipment carrier and tracking code are mandatory before shipped status.',
);

$gate(
    'refund_pending_is_not_fake_cancellation',
    str_contains($files['service'], 'OrderStatus::RefundPending')
        && str_contains($files['service'], 'SubOrderStatus::RefundPending')
        && ! str_contains($files['service'], 'SubOrderStatus::Rejected => OrderStatus::Cancelled'),
    'A paid rejected order must remain financially pending until a real refund is recorded.',
);

$gate(
    'exactly_once_restock',
    str_contains($files['migration'], "unique(['order_item_id', 'reason'])")
        && str_contains($files['service'], 'restockPaidOrderExactlyOnce')
        && str_contains($files['service'], 'InventoryRestock::query()')
        && str_contains($files['service'], 'StockReason::Return')
        && str_contains($files['service'], 'stock_on_hand'),
    'Paid rejected items must be restocked once with an append-only stock ledger entry.',
);

$gate(
    'append_only_operational_evidence',
    str_contains($files['history'], 'append-only')
        && str_contains($files['note'], 'append-only')
        && str_contains($files['restock'], 'append-only')
        && str_contains($files['migration'], "Schema::create('sub_order_status_history'")
        && str_contains($files['migration'], "Schema::create('order_internal_notes'"),
    'Status history, internal notes and restock evidence must not be mutable.',
);

$gate(
    'customer_safe_shipment_contract',
    str_contains($files['resource'], "'carrier' => \$shipment->carrier")
        && str_contains($files['resource'], "'tracking_code' => \$shipment->tracking_code")
        && ! str_contains($files['resource'], 'internalNotes')
        && ! str_contains($files['resource'], 'metadata'),
    'Customers receive shipment tracking without internal notes, actors or audit metadata.',
);

$gate(
    'whole_bean_boundary',
    ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files)),
    'Fulfillment work must not introduce grind selection or grind state.',
);

$failed = array_values(array_filter($gates, static fn (array $item): bool => ! $item['passed']));
$report = [
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'marketplace_fulfillment=ready',
    'passed' => $failed === [],
    'gates' => $gates,
];
file_put_contents(
    $root.'/fulfillment-contract-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
);

if ($failed !== []) {
    fwrite(STDERR, "Fulfillment contract audit failed:\n");
    foreach ($failed as $item) {
        fwrite(STDERR, "- {$item['name']}: {$item['evidence']}\n");
    }
    exit(1);
}

echo 'Fulfillment contract audit passed ('.count($gates)." gates).\n";
