<?php

$root = dirname(__DIR__);
$files = [
    'delivery' => file_get_contents($root.'/app/Services/Fulfillment/DeliveryConfirmationService.php'),
    'release' => file_get_contents($root.'/app/Services/Settlement/SettlementReleaseService.php'),
    'batch' => file_get_contents($root.'/app/Services/Settlement/SettlementBatchService.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_07_27_190001_create_r5i_delivery_settlement_payouts.php'),
    'delivery_routes' => file_get_contents($root.'/routes/fulfillment.php'),
    'finance_routes' => file_get_contents($root.'/routes/finance.php'),
    'console' => file_get_contents($root.'/routes/console.php'),
    'resource' => file_get_contents($root.'/app/Http/Resources/OrderResource.php'),
    'test' => file_get_contents($root.'/tests/Feature/R5IDeliverySettlementTest.php'),
];

$failures = [];
$require = static function (string $name, string $key, array $needles) use (&$failures, $files): void {
    foreach ($needles as $needle) {
        if (! str_contains($files[$key], $needle)) {
            $failures[] = $name.': missing '.$needle;
        }
    }
};

$require('delivery idempotency and final-leg boundary', 'delivery', [
    'delivery.idempotency_conflict',
    'delivery.customer_final_leg_only',
    'intermediate_leg_delivered',
    'dispute_window_ends_at',
    'syncOrderStatus',
]);
$require('release boundary', 'release', [
    "->where('owner_type', 'roastery')",
    'SettlementAllocationStatus::Eligible',
    'FulfillmentIncidentStatus::Open',
    'ShipmentLegStatus::Lost',
    'settlement.allocations_released',
]);
$require('single batch membership and payout states', 'batch', [
    'settlement_batch_allocations',
    'SettlementAllocationStatus::Scheduled',
    'SettlementAllocationStatus::Paid',
    'settlement.batch_idempotency_conflict',
    'settlement.no_eligible_allocations',
]);
$require('schema invariants', 'migration', [
    "Schema::create('shipment_delivery_confirmations'",
    "Schema::create('settlement_batches'",
    "Schema::create('settlement_batch_allocations'",
    "unique('settlement_allocation_id'",
    "unique(['shipment_leg_id']",
]);
$require('delivery routes', 'delivery_routes', [
    '/webhooks/carriers/deliveries',
    '/orders/{orderId}/shipment-legs/{shipmentLegId}/delivery-confirmations',
    '/admin/shipment-legs/{shipmentLegId}/delivery-confirmations',
]);
$require('finance routes', 'finance_routes', [
    '/admin/finance/settlement-batches',
    '/admin/finance/settlement-batches/{batchId}/resolve',
    '/seller/roasteries/{roasteryId}/settlements',
]);
$require('release schedule', 'console', [
    'SettlementReleaseService',
    'rosta.settlement.release-eligible',
]);
$require('safe customer resource', 'resource', [
    "'customer_can_confirm'",
    "'settlement_state'",
    "'delivery_confirmation'",
    "'proof_type'",
]);
$require('acceptance coverage', 'test', [
    'test_final_delivery_releases_only_roastery_money_and_pays_one_idempotent_batch',
    'test_hub_first_leg_delivery_never_completes_customer_delivery',
    "'owner_type' => 'rosta'",
    "'status' => 'held'",
    "'status' => 'paid'",
]);

foreach (['hub_variant', 'hub_sku', 'grinding_inventory', 'grind_variant'] as $forbidden) {
    if (str_contains(implode("\n", $files), $forbidden)) {
        $failures[] = 'whole-bean boundary: forbidden '.$forbidden;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "R5I delivery settlement audit failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "ROSTA_R5I_DELIVERY_SETTLEMENT_COMPLETE\n";
