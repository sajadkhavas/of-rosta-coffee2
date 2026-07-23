<?php

$root = dirname(__DIR__);
$openApi = implode("\n", [
    file_get_contents(dirname($root).'/docs/openapi/rosta-v1-commerce-additions.yaml'),
    file_get_contents(dirname($root).'/docs/openapi/rosta-v1-finance.yaml'),
    file_get_contents(dirname($root).'/docs/openapi/rosta-v1-seller-operations.yaml'),
    file_get_contents(dirname($root).'/docs/openapi/rosta-v1-admin-operations.yaml'),
]);
$routeSources = implode("\n", array_map(
    static fn (string $file): string => file_get_contents($root.'/routes/'.$file),
    [
        'api.php',
        'payments.php',
        'fulfillment.php',
        'reviews-support.php',
        'media-uploads.php',
        'finance.php',
        'seller-bootstrap.php',
        'admin-operations.php',
    ],
));

$contracts = [
    '/payments/request',
    '/payments/{paymentId}/verify',
    '/seller/roasteries',
    '/seller/roasteries/{roasteryId}/products',
    '/seller/roasteries/{roasteryId}/products/{productId}/variants',
    '/seller/roasteries/{roasteryId}/variants/{variantId}/stock-adjustments',
    '/seller/roasteries/{roasteryId}/orders/{orderId}/fulfillment',
    '/seller/roasteries/{roasteryId}/media/uploads',
    '/seller/roasteries/{roasteryId}/media/uploads/{uploadId}/complete',
    '/admin/orders/{orderId}/fulfillment',
    '/reviews',
    '/products/{productSlug}/reviews',
    '/admin/reviews',
    '/admin/reviews/{reviewId}',
    '/inquiries',
    '/admin/inquiries',
    '/admin/inquiries/{inquiryId}',
    '/admin/roasteries',
    '/admin/roasteries/{roasteryId}/status',
    '/admin/products',
    '/admin/products/{productId}/status',
    '/admin/operations/audits',
    '/admin/operations/notifications',
    '/admin/finance/refunds',
    '/admin/finance/reconciliation',
    '/admin/orders/{orderId}/refunds',
    '/admin/refunds/{refundId}/approve',
    '/admin/refunds/{refundId}/dispatch',
    '/admin/refunds/{refundId}/resolve',
    '/admin/finance/reconciliation/{caseId}',
];

$missingRoutes = [];
$missingOpenApi = [];
foreach ($contracts as $path) {
    if (! str_contains($routeSources, $path)) {
        $missingRoutes[] = $path;
    }
    if (! str_contains($openApi, $path.':')) {
        $missingOpenApi[] = $path;
    }
}

$passed = $missingRoutes === [] && $missingOpenApi === [];
file_put_contents($root.'/commerce-openapi-drift-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'commerce_openapi_drift=clean',
    'passed' => $passed,
    'missingRoutes' => $missingRoutes,
    'missingOpenApi' => $missingOpenApi,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (! $passed) {
    fwrite(STDERR, "Commerce OpenAPI drift detected.\n");
    foreach ($missingRoutes as $path) {
        fwrite(STDERR, "- Missing route: {$path}\n");
    }
    foreach ($missingOpenApi as $path) {
        fwrite(STDERR, "- Missing OpenAPI path: {$path}\n");
    }
    exit(1);
}

echo 'Commerce OpenAPI drift audit passed ('.count($contracts)." paths).\n";
