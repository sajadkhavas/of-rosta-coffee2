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

$failed = array_values(array_filter($gates, static fn (array $item): bool => ! $item['passed']));
file_put_contents($root.'/admin-operations-contract-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'admin_operations_contract=ready',
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
