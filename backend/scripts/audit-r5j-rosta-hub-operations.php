<?php

$root = dirname(__DIR__);
$files = [
    'migration' => file_get_contents($root.'/database/migrations/2026_07_28_000001_create_r5j_hub_operations.php'),
    'service' => file_get_contents($root.'/app/Services/Hub/RostaHubOperationsService.php'),
    'routes' => file_get_contents($root.'/routes/hub-operations.php'),
    'resource' => file_get_contents($root.'/app/Http/Resources/HubWorkItemResource.php'),
    'order' => '',
];
$files['order'] = file_get_contents($root.'/app/Services/Checkout/OrderService.php');
$failures = [];
$required = [
    'migration' => ['hub_work_items', 'hub_work_item_actions', 'private_evidence', 'snapshot'],
    'service' => ['inbound_not_delivered', 'quality_fail', 'rework_required', 'ready_for_outbound', 'hub.operation.', 'lockForUpdate', 'assigned_operator_id'],
    'routes' => ['hub_operator,administrator', '/admin/hub-operations', '/hub-operations'],
    'resource' => ['public_label', 'assigned_operator', 'actions'],
    'order' => ['createForRoute', 'hubWorkItem'],
];
foreach ($required as $name => $needles) {
    foreach ($needles as $needle) {
        if (! str_contains((string) $files[$name], $needle)) {
            $failures[] = $name.' missing '.$needle;
        }
    }
}
if (str_contains((string) $files['resource'], 'private_evidence')) {
    $failures[] = 'resource exposes private evidence';
}
if ($failures !== []) {
    fwrite(STDERR, 'R5J audit failed:
- '.implode('
- ', $failures).'
');
    exit(1);
}
fwrite(STDOUT, 'ROSTA_R5J_HUB_OPERATIONS_COMPLETE
');
