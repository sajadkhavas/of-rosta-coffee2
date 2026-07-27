<?php

$root = dirname(__DIR__);
$files = [
    'payment' => file_get_contents($root.'/app/Services/Payments/PaymentService.php'),
    'commitment' => file_get_contents($root.'/app/Services/Fulfillment/FulfillmentCommitmentService.php'),
    'fulfillment' => file_get_contents($root.'/app/Services/Fulfillment/FulfillmentService.php'),
    'incident' => file_get_contents($root.'/app/Services/Fulfillment/FulfillmentIncidentService.php'),
    'sla' => file_get_contents($root.'/app/Services/Fulfillment/FulfillmentSlaMonitorService.php'),
    'console' => file_get_contents($root.'/routes/console.php'),
    'request' => file_get_contents($root.'/app/Http/Requests/Fulfillment/UpdateFulfillmentRequest.php'),
    'routes' => file_get_contents($root.'/routes/fulfillment.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_07_27_160001_create_r5h_fulfillment_commitments_and_incidents.php'),
    'resource' => file_get_contents($root.'/app/Http/Resources/OrderResource.php'),
    'test' => file_get_contents($root.'/tests/Feature/R5HFulfillmentCommitmentTest.php'),
];

$failures = [];
$require = static function (string $name, array $needles, string $file) use (&$failures, $files): void {
    foreach ($needles as $needle) {
        if (! str_contains($files[$file], $needle)) {
            $failures[] = $name.': missing '.$needle;
        }
    }
};

$require('automatic commitment', [
    'FulfillmentCommitmentService',
    'commitPaidOrder',
], 'payment');
$require('commitment snapshots', [
    'SubOrderStatus::Accepted',
    'SubOrderAcceptanceStatus::Accepted',
    'payment_verified_contractual_commitment',
    'preparation_due_at',
    'handoff_due_at',
], 'commitment');
$require('seller transition boundary', [
    'SubOrderStatus::Preparing',
    'SubOrderStatus::ReadyToShip',
    'SubOrderStatus::Shipped',
    'allowDelivery',
    'fulfillment.incident_resolution_required',
], 'fulfillment');
if (str_contains($files['request'], "'accepted'") || str_contains($files['request'], "'rejected'")) {
    $failures[] = 'seller request must not accept accepted/rejected transitions';
}
$require('incident control', [
    'restockExactlyOnce',
    'reverseAllocations',
    'admin_exception_cancelled',
    "'amount' => \$subOrder->grand_total",
    'OrderStatus::PartiallyCancelled',
], 'incident');
$require('sla enforcement', [
    'markBreaches',
    'fulfillment.sla_breached',
    'FulfillmentSlaStatus::Breached',
], 'sla');
$require('sla schedule', [
    'FulfillmentSlaMonitorService',
    "rosta.fulfillment.mark-sla-breaches",
], 'console');
$require('incident schema', [
    "Schema::create('fulfillment_incidents'",
    'fulfillment_committed_at',
    'preparation_due_at',
    'handoff_due_at',
    'sla_status',
], 'migration');
$require('routes', [
    '/orders/{orderId}/incidents',
    '/admin/fulfillment-incidents',
    '/admin/fulfillment-incidents/{incidentId}/resolve',
], 'routes');
$require('customer-safe contract', [
    "'acceptance_mode'",
    "'is_breached'",
    "'incidents'",
], 'resource');
$require('multi-sub-order acceptance', [
    'test_admin_exception_cancels_and_refunds_only_the_affected_sub_order',
    "'status' => 'reversed'",
    "'status' => 'held'",
    "'amount' => 4_300_000",
    'test_sla_monitor_marks_breach_once_without_touching_healthy_siblings',
], 'test');

foreach (['hub_variant', 'hub_sku', 'grinding_inventory', 'grind_variant'] as $forbidden) {
    if (str_contains(implode("\n", $files), $forbidden)) {
        $failures[] = 'whole-bean boundary: forbidden '.$forbidden;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "R5H fulfillment audit failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "ROSTA_R5H_FULFILLMENT_COMMITMENT_COMPLETE\n";
