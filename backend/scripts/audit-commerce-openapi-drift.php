<?php

$root = dirname(__DIR__);
$openApi = implode("\n", [
    file_get_contents(dirname($root).'/docs/openapi/rosta-v1-commerce-additions.yaml'),
    file_get_contents(dirname($root).'/docs/openapi/rosta-v1-finance.yaml'),
    file_get_contents(dirname($root).'/docs/openapi/rosta-v1-seller-operations.yaml'),
    file_get_contents(dirname($root).'/docs/openapi/rosta-v1-admin-operations.yaml'),
    file_get_contents(dirname($root).'/docs/openapi/rosta-v1-quiz-reviews.yaml'),
]);
require_once $root.'/scripts/route-contract-support.php';
$routeContracts = rostaRouteContracts($root);

$contracts = [
    '/payments/request',
    '/payments/{paymentId}/verify',
    '/seller/roasteries',
    '/seller/roasteries/{roasteryId}/products',
    '/seller/roasteries/{roasteryId}/products/{productId}/variants',
    '/seller/roasteries/{roasteryId}/variants/{variantId}/stock-adjustments',
    '/seller/roasteries/{roasteryId}/orders/{orderId}/fulfillment',
    '/seller/roasteries/{roasteryId}/orders/{orderId}/incidents',
    '/seller/roasteries/{roasteryId}/media/uploads',
    '/seller/roasteries/{roasteryId}/media/uploads/{uploadId}/complete',
    '/admin/orders/{orderId}/fulfillment',
    '/admin/fulfillment-incidents',
    '/admin/fulfillment-incidents/{incidentId}/resolve',
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
    '/orders/{orderId}/shipment-legs/{shipmentLegId}/delivery-confirmations',
    '/admin/shipment-legs/{shipmentLegId}/delivery-confirmations',
    '/webhooks/carriers/deliveries',
    '/admin/finance/settlement-batches',
    '/admin/finance/settlement-batches/{batchId}/resolve',
    '/admin/sub-orders/{subOrderId}/settlement-hold',
    '/seller/roasteries/{roasteryId}/settlements',
    '/quiz/current',
    '/quiz/attempts',
    '/quiz/attempts/{attemptId}',
    '/quiz/attempts/{attemptId}/recommendations',
    '/quiz/attempts/{attemptId}/sync',
    '/me/quiz-attempts',
    '/me/quiz-attempts/{attemptId}',
    '/reviews/{reviewId}/reports',
    '/seller/roasteries/{roasteryId}/reviews',
    '/seller/roasteries/{roasteryId}/reviews/{reviewId}/reply',
    '/admin/quiz/versions',
    '/admin/quiz/versions/{versionId}',
    '/admin/quiz/versions/{versionId}/preview',
    '/admin/quiz/versions/{versionId}/publish',
    '/admin/quiz/versions/{versionId}/archive',
    '/admin/review-reports',
    '/admin/review-reports/{reportId}',
    '/admin/review-replies',
    '/admin/review-replies/{replyId}',
];

$missingRoutes = [];
$missingOpenApi = [];
foreach ($contracts as $path) {
    if (! isset($routeContracts[$path])) $missingRoutes[] = $path;
    if (! str_contains($openApi, $path.':')) $missingOpenApi[] = $path;
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
    foreach ($missingRoutes as $path) fwrite(STDERR, "- Missing route: {$path}\n");
    foreach ($missingOpenApi as $path) fwrite(STDERR, "- Missing OpenAPI path: {$path}\n");
    exit(1);
}

echo 'Commerce OpenAPI drift audit passed ('.count($contracts)." paths).\n";
