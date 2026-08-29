<?php

$root = dirname(__DIR__);
$files = [
    'routes' => file_get_contents($root.'/routes/admin-operations.php'),
    'controller' => file_get_contents($root.'/app/Http/Controllers/Admin/AdminOperationsController.php'),
    'roasteries' => file_get_contents($root.'/app/Http/Controllers/Admin/AdminRoasteryController.php'),
    'products' => file_get_contents($root.'/app/Http/Controllers/Admin/AdminProductController.php'),
    'reviews' => file_get_contents($root.'/app/Http/Controllers/Admin/AdminReviewController.php'),
    'inquiries' => file_get_contents($root.'/app/Http/Controllers/Admin/AdminInquiryController.php'),
    'provider' => file_get_contents($root.'/app/Providers/AppServiceProvider.php'),
    'bootstrap' => file_get_contents($root.'/bootstrap/app.php'),
    'carrier_routes' => file_get_contents($root.'/routes/carrier-operations.php'),
    'fulfillment_routes' => file_get_contents($root.'/routes/fulfillment.php'),
    'carrier_middleware' => file_get_contents($root.'/app/Http/Middleware/VerifyCarrierWebhookSignature.php'),
    'carrier_service' => file_get_contents($root.'/app/Services/Carrier/CarrierOperationsService.php'),
    'manual_provider' => file_get_contents($root.'/app/Services/Carrier/ManualCarrierProvider.php'),
    'failed_controller' => file_get_contents($root.'/app/Http/Controllers/Admin/AdminFailedJobController.php'),
    'failed_service' => file_get_contents($root.'/app/Services/Operations/FailedJobOperationsService.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_08_29_090001_create_ps5c_carrier_operations_tables.php'),
    'openapi' => file_get_contents(dirname($root).'/docs/openapi/rosta-v1-carrier-admin-operations.yaml'),
    'phase_doc' => file_get_contents(dirname($root).'/docs/PS5_3_CARRIER_ADMIN_OPERATIONS.md'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$gate(
    'administrator_only_routes',
    str_contains($files['routes'], "'auth:sanctum', 'rosta.session', 'rosta.role:administrator'")
        && str_contains($files['routes'], '/admin/operations')
        && str_contains($files['provider'], "'admin-operations.php'"),
    'Audit and notification operations must be loaded only behind active-session administrator middleware.',
);

$gate(
    'notification_payload_is_never_exposed',
    str_contains($files['controller'], "'destination_hint'")
        && str_contains($files['controller'], 'maskDestination')
        && ! str_contains($files['controller'], "'payload' => \$item->payload")
        && ! str_contains($files['controller'], "'destination' => \$item->destination"),
    'Notification payloads and full destinations must remain outside administrator browser responses.',
);

$gate(
    'audit_metadata_is_redacted_and_read_only',
    str_contains($files['controller'], 'AuditLog::query()')
        && str_contains($files['controller'], 'redactMetadata')
        && str_contains($files['controller'], "'[redacted]'")
        && ! str_contains($files['routes'], 'Route::patch'),
    'Audit access must be read-only and sensitive metadata keys must be redacted recursively.',
);

$gate(
    'moderation_queues_are_filterable',
    str_contains($files['roasteries'], "\$query->where('status', \$status)")
        && str_contains($files['roasteries'], "'status' => \$roastery->status->value")
        && str_contains($files['products'], "\$query->where('status', \$status)")
        && str_contains($files['reviews'], "\$query->where('status', \$status)")
        && str_contains($files['inquiries'], "\$query->where('status', \$status)"),
    'Roastery, product, review and inquiry queues must expose explicit lifecycle filters.',
);

$gate(
    'ps5c_routes_are_loaded_and_role_scoped',
    str_contains($files['provider'], "'carrier-operations.php'")
        && str_contains($files['carrier_routes'], '/webhooks/carriers/events')
        && str_contains($files['carrier_routes'], '/admin/shipment-legs/{shipmentLegId}/carrier')
        && str_contains($files['carrier_routes'], "'rosta.role:administrator'")
        && str_contains($files['fulfillment_routes'], "'rosta.carrier.webhook'"),
    'PS5.3 carrier/admin routes must be registered without bypassing administrator or webhook middleware.',
);

$gate(
    'carrier_webhook_is_fresh_signed_and_replay_safe',
    str_contains($files['bootstrap'], "'rosta.carrier.webhook'")
        && str_contains($files['carrier_middleware'], "hash_hmac('sha256'")
        && str_contains($files['carrier_middleware'], 'hash_equals')
        && str_contains($files['carrier_middleware'], 'X-Rosta-Carrier-Timestamp')
        && str_contains($files['carrier_middleware'], 'X-Rosta-Carrier-Event-Id')
        && str_contains($files['carrier_middleware'], 'webhook_tolerance_seconds')
        && str_contains($files['carrier_service'], 'carrier.webhook_replay_conflict')
        && str_contains($files['migration'], "unique()")
        && str_contains($files['migration'], "char('payload_hash', 64)"),
    'Carrier events require HMAC freshness and persistent event-id/payload-hash replay protection.',
);

$gate(
    'manual_carrier_does_not_fabricate_provider_automation',
    str_contains($files['manual_provider'], 'supportsAutomatedDispatch(): bool')
        && str_contains($files['manual_provider'], 'return false;')
        && str_contains($files['carrier_service'], 'carrier.delivery_confirmation_required')
        && str_contains($files['carrier_service'], 'DeliveryConfirmationService')
        && ! str_contains($files['carrier_service'], 'Http::')
        && ! str_contains($files['carrier_service'], 'SettlementAllocation')
        && ! str_contains($files['carrier_service'], 'RefundAttempt'),
    'Manual carrier is the only implemented provider and cannot bypass proof-driven delivery or mutate finance.',
);

$gate(
    'failed_job_browser_is_redacted_and_retry_is_exact',
    str_contains($files['failed_controller'], "select(['id', 'uuid', 'connection', 'queue', 'payload', 'failed_at'])")
        && ! str_contains($files['failed_controller'], "'payload' =>")
        && ! str_contains($files['failed_controller'], "'exception' =>")
        && str_contains($files['failed_service'], "Artisan::call('queue:retry'")
        && str_contains($files['failed_service'], "['id' => [\$uuid]]"),
    'Failed-job output must omit serialized payload/trace and retry exactly one operator-selected UUID.',
);

$gate(
    'failed_job_forget_requires_dual_control',
    str_contains($files['failed_service'], 'operations.failed_job_dual_control_required')
        && str_contains($files['failed_service'], "Artisan::call('queue:forget'")
        && str_contains($files['migration'], "Schema::create('failed_job_operations'")
        && str_contains($files['carrier_routes'], 'forget-requests/{operationId}/confirm'),
    'Destructive failed-job deletion must be requested then confirmed by another administrator.',
);

$gate(
    'ps5c_openapi_and_provider_truth_are_registered',
    str_contains($files['openapi'], 'X-Rosta-Carrier-Signature')
        && str_contains($files['openapi'], '/admin/operations/failed-jobs/{uuid}/retry')
        && str_contains($files['phase_doc'], 'Laravel 13 queue documentation')
        && str_contains($files['phase_doc'], 'No native Iran Post, Tipax or other carrier API is claimed'),
    'PS5.3 must document its API and explicitly distinguish Rosta-defined contracts from unproven carrier APIs.',
);

$failed = array_values(array_filter($gates, static fn (array $item): bool => ! $item['passed']));
file_put_contents($root.'/admin-operations-contract-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'admin_operations_contract=ready',
    'ps5c_marker' => 'ps5c_carrier_admin_operations=ready',
    'passed' => $failed === [],
    'gates' => $gates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, "Admin operations contract audit failed:\n");
    foreach ($failed as $item) {
        fwrite(STDERR, "- {$item['name']}: {$item['evidence']}\n");
    }
    exit(1);
}

echo 'Admin operations contract audit passed ('.count($gates)." gates).\n";
