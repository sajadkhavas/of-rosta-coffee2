<?php

$root = dirname(__DIR__);
$files = [
    'api_routes' => file_get_contents($root.'/routes/api.php'),
    'fulfillment_routes' => file_get_contents($root.'/routes/fulfillment.php'),
    'media_routes' => file_get_contents($root.'/routes/media-uploads.php'),
    'roastery_controller' => file_get_contents($root.'/app/Http/Controllers/Seller/SellerRoasteryController.php'),
    'access' => file_get_contents($root.'/app/Services/Catalog/CatalogAccess.php'),
    'variant_request' => file_get_contents($root.'/app/Http/Requests/Catalog/UpsertVariantRequest.php'),
    'stock_request' => file_get_contents($root.'/app/Http/Requests/Catalog/AdjustStockRequest.php'),
    'fulfillment_request' => file_get_contents($root.'/app/Http/Requests/Fulfillment/UpdateFulfillmentRequest.php'),
    'media_service' => file_get_contents($root.'/app/Services/Media/MediaUploadService.php'),
    'openapi' => file_get_contents(dirname($root).'/docs/openapi/rosta-v1-seller-operations.yaml'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$gate(
    'accessible_roastery_bootstrap_is_scoped',
    str_contains($files['api_routes'], "Route::get('/seller/roasteries'")
        && str_contains($files['roastery_controller'], "scope_type === 'roastery'")
        && str_contains($files['roastery_controller'], "whereIn('id', \$rolesByRoastery->keys()->all())")
        && str_contains($files['roastery_controller'], "'access_roles'")
        && str_contains($files['access'], 'catalog.roastery_forbidden'),
    'The seller panel may bootstrap only roasteries granted by scoped roles; administrators are the explicit global exception.',
);

$gate(
    'whole_bean_weights_are_fixed',
    str_contains($files['variant_request'], 'Rule::in(config(\'rosta.whole_bean_weights\'))')
        && ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files)),
    'Seller variants must remain whole-bean weight SKUs with no grind state.',
);

$gate(
    'inventory_is_ledger_and_idempotency_based',
    str_contains($files['stock_request'], "'idempotency_key'")
        && str_contains($files['stock_request'], 'StockReason::manualValues()')
        && str_contains($files['api_routes'], '/stock-ledger')
        && str_contains($files['api_routes'], '/stock-adjustments'),
    'Seller inventory writes must use bounded, reasoned and idempotent ledger adjustments.',
);

$gate(
    'fulfillment_state_machine_contract',
    str_contains($files['fulfillment_routes'], '/fulfillment')
        && str_contains($files['fulfillment_request'], "'tracking_code'")
        && str_contains($files['fulfillment_request'], "'carrier'")
        && str_contains($files['fulfillment_request'], 'SubOrderStatus::ReadyToShip->value')
        && str_contains($files['fulfillment_request'], 'SubOrderStatus::Delivered->value'),
    'Seller fulfillment must use the domain state machine and require carrier/tracking data for shipment.',
);

$gate(
    'media_upload_is_backend_keyed_and_checksum_bound',
    str_contains($files['media_routes'], '/media/uploads')
        && str_contains($files['media_service'], 'temporaryUploadUrl')
        && str_contains($files['media_service'], 'checksum_sha256')
        && str_contains($files['media_service'], 'object_key')
        && str_contains($files['media_service'], 'roastery_id'),
    'The browser must upload only through a scoped signed intent whose key and checksum are controlled by the backend.',
);

$gate(
    'seller_openapi_present',
    str_contains($files['openapi'], '/seller/roasteries:')
        && str_contains($files['openapi'], '/products/{productId}/variants:')
        && str_contains($files['openapi'], '/stock-adjustments:')
        && str_contains($files['openapi'], '/orders/{orderId}/fulfillment:')
        && str_contains($files['openapi'], '/media/uploads/{uploadId}/complete:'),
    'Seller bootstrap, catalog, inventory, fulfillment and media operations must remain represented in OpenAPI.',
);

$failed = array_values(array_filter($gates, static fn (array $item): bool => ! $item['passed']));
file_put_contents($root.'/seller-operations-contract-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'seller_operations_contract=ready',
    'passed' => $failed === [],
    'gates' => $gates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, "Seller operations contract audit failed:\n");
    foreach ($failed as $item) {
        fwrite(STDERR, "- {$item['name']}: {$item['evidence']}\n");
    }
    exit(1);
}

echo 'Seller operations contract audit passed ('.count($gates)." gates).\n";
