<?php

$root = dirname(__DIR__);
$files = [
    'service' => file_get_contents($root.'/app/Services/Fulfillment/FulfillmentService.php'),
    'commitment' => file_get_contents($root.'/app/Services/Fulfillment/FulfillmentCommitmentService.php'),
    'incident' => file_get_contents($root.'/app/Services/Fulfillment/FulfillmentIncidentService.php'),
    'request' => file_get_contents($root.'/app/Http/Requests/Fulfillment/UpdateFulfillmentRequest.php'),
    'routes' => file_get_contents($root.'/routes/fulfillment.php'),
    'seller' => file_get_contents($root.'/app/Http/Controllers/Seller/SellerOrderController.php'),
    'admin' => file_get_contents($root.'/app/Http/Controllers/Admin/AdminOrderController.php'),
    'history' => file_get_contents($root.'/app/Models/SubOrderStatusHistory.php'),
    'note' => file_get_contents($root.'/app/Models/OrderInternalNote.php'),
    'restock' => file_get_contents($root.'/app/Models/InventoryRestock.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_07_22_200001_create_fulfillment_tables.php'),
    'r5hMigration' => file_get_contents($root.'/database/migrations/2026_07_27_160001_create_r5h_fulfillment_commitments_and_incidents.php'),
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
    'contractual_auto_commitment',
    str_contains($files['commitment'], 'payment_verified_contractual_commitment')
        && str_contains($files['commitment'], 'SubOrderStatus::Accepted')
        && str_contains($files['commitment'], 'preparation_due_at')
        && str_contains($files['commitment'], 'handoff_due_at'),
    'Paid sub-orders must be accepted automatically with immutable operational deadlines.',
);

$gate(
    'seller_cannot_accept_or_reject',
    ! str_contains($files['request'], "'accepted'")
        && ! str_contains($files['request'], "'rejected'")
        && str_contains($files['service'], 'allowDelivery')
        && str_contains($files['service'], 'fulfillment.transition_invalid'),
    'Seller actions are preparation, ready-to-ship and handoff only; acceptance/rejection is forbidden.',
);

$gate(
    'tracking_required_before_shipping',
    str_contains($files['request'], 'required_if:status,shipped')
        && str_contains($files['service'], 'fulfillment.tracking_required')
        && str_contains($files['service'], "'tracking_code' => \$trackingCode"),
    'Shipment carrier and tracking code are mandatory before handoff.',
);

$gate(
    'incident_is_non_destructive_until_admin_resolution',
    str_contains($files['incident'], 'fulfillment.incident_reported')
        && str_contains($files['incident'], 'FulfillmentSlaStatus::ExceptionOpen')
        && str_contains($files['service'], 'fulfillment.incident_resolution_required')
        && str_contains($files['routes'], '/orders/{orderId}/incidents'),
    'Seller incidents pause operational transitions without self-cancelling, refunding or restocking.',
);

$gate(
    'admin_scoped_refund_and_exactly_once_restock',
    str_contains($files['incident'], 'restockExactlyOnce')
        && str_contains($files['incident'], 'reverseAllocations')
        && str_contains($files['incident'], "'amount' => \$subOrder->grand_total")
        && str_contains($files['migration'], "unique(['order_item_id', 'reason'])")
        && str_contains($files['incident'], 'InventoryRestock::query()')
        && str_contains($files['incident'], 'StockReason::Return'),
    'Only an administrator may refund the affected sub-order and restock each order item once.',
);

$gate(
    'append_only_operational_evidence',
    str_contains($files['history'], 'append-only')
        && str_contains($files['note'], 'append-only')
        && str_contains($files['restock'], 'append-only')
        && str_contains($files['r5hMigration'], "Schema::create('fulfillment_incidents'"),
    'Status history, notes, restocks and incidents preserve operational evidence.',
);

$gate(
    'customer_safe_shipment_and_incident_contract',
    str_contains($files['resource'], "'shipment_legs' => \$subOrder->shipmentLegs")
        && str_contains($files['resource'], "'incidents'")
        && ! str_contains($files['resource'], 'resolution_note')
        && ! str_contains($files['resource'], "'description' => \$incident->description")
        && ! str_contains($files['resource'], 'internalNotes'),
    'Customers receive truthful tracking and safe incident state without private operational notes.',
);

$gate(
    'whole_bean_boundary',
    ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files)),
    'Fulfillment work must not introduce product or inventory grind state.',
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
