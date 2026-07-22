<?php

$root = dirname(__DIR__);
$files = [
    'payment_service' => file_get_contents($root.'/app/Services/Payments/PaymentService.php'),
    'payment_manager' => file_get_contents($root.'/app/Services/Payments/PaymentProviderManager.php'),
    'payment_routes' => file_get_contents($root.'/routes/payments.php'),
    'payment_model' => file_get_contents($root.'/app/Models/PaymentAttempt.php'),
    'outbox_service' => file_get_contents($root.'/app/Services/Notifications/NotificationOutboxService.php'),
    'outbox_model' => file_get_contents($root.'/app/Models/NotificationOutbox.php'),
    'sms_manager' => file_get_contents($root.'/app/Services/Notifications/SmsProviderManager.php'),
    'observer' => file_get_contents($root.'/app/Observers/OrderObserver.php'),
    'config' => file_get_contents($root.'/config/rosta.php'),
    'payment_migration' => file_get_contents($root.'/database/migrations/2026_07_22_190001_create_payment_attempts.php'),
    'outbox_migration' => file_get_contents($root.'/database/migrations/2026_07_22_190002_create_notification_outbox.php'),
    'frontend_client' => file_get_contents(dirname($root).'/src/lib/api/checkout.ts'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$gate(
    'frontend_backend_payment_contract',
    str_contains($files['frontend_client'], 'apiFetch("/payments/request"')
        && str_contains($files['frontend_client'], '`/payments/${encodeURIComponent(normalizedPaymentId)}/verify`')
        && str_contains($files['payment_routes'], "'/payments/request'")
        && str_contains($files['payment_routes'], "'/payments/{paymentId}/verify'"),
    'The Laravel routes must satisfy the already-frozen frontend payment client.',
);

$gate(
    'provider_neutral_payment',
    str_contains($files['payment_manager'], "'disabled'")
        && str_contains($files['payment_manager'], "'testing'")
        && str_contains($files['payment_manager'], "'zarinpal'")
        && str_contains($files['payment_manager'], "! app()->environment('production')"),
    'Payments must remain provider-neutral and the testing adapter must be denied in production.',
);

$gate(
    'idempotent_attempt_history',
    str_contains($files['payment_migration'], "unique(['order_id', 'idempotency_key'])")
        && str_contains($files['payment_migration'], "unique(['order_id', 'attempt_number'])")
        && str_contains($files['payment_model'], "'request_hash'")
        && str_contains($files['payment_service'], 'payment.idempotency_conflict'),
    'Every payment attempt must be immutable, numbered and request-bound.',
);

$gate(
    'atomic_marketplace_inventory_settlement',
    str_contains($files['payment_service'], "'stock_on_hand' => \$variant->stock_on_hand - \$reservation->quantity")
        && str_contains($files['payment_service'], "'stock_reserved' => \$variant->stock_reserved - \$reservation->quantity")
        && str_contains($files['payment_service'], 'ReservationStatus::Consumed')
        && str_contains($files['payment_service'], 'lockForUpdate()'),
    'Verified payment must atomically consume both on-hand and reserved stock.',
);

$gate(
    'paid_but_unallocated_boundary',
    str_contains($files['payment_service'], 'PaymentAttemptStatus::RequiresReview')
        && str_contains($files['payment_service'], 'inventory_reconciliation_required')
        && str_contains($files['payment_service'], 'reservation_unavailable'),
    'Confirmed gateway truth must not be mislabeled failed when inventory needs reconciliation.',
);

$gate(
    'encrypted_provider_payloads',
    str_contains($files['payment_model'], "'request_payload' => 'encrypted:array'")
        && str_contains($files['payment_model'], "'verification_payload' => 'encrypted:array'")
        && str_contains($files['outbox_model'], "'destination' => 'encrypted'")
        && str_contains($files['outbox_model'], "'payload' => 'encrypted:array'"),
    'Provider payloads, notification destinations and template data must remain encrypted at rest.',
);

$gate(
    'durable_notification_outbox',
    str_contains($files['outbox_service'], 'NotificationStatus::Processing')
        && str_contains($files['outbox_service'], 'stale_processing_recovered')
        && str_contains($files['outbox_service'], 'dispatchPending')
        && str_contains($files['observer'], "'order.paid'")
        && str_contains($files['outbox_migration'], "deduplication_key', 190)->nullable()->unique()"),
    'Order notifications must be transactionally queued, deduplicated and retried outside the business transaction.',
);

$gate(
    'sms_provider_activation_boundary',
    str_contains($files['sms_manager'], "'disabled'")
        && str_contains($files['sms_manager'], "'testing'")
        && str_contains($files['sms_manager'], "'kavenegar'")
        && str_contains($files['sms_manager'], "! app()->environment('production')")
        && str_contains($files['config'], "'enabled' => \$smsEnabled"),
    'SMS delivery is disabled by default and testing delivery is non-production only.',
);

$gate(
    'whole_bean_boundary',
    ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files)),
    'Cooci adoption must never introduce grind selection or grind state into Rosta.',
);

$failed = array_values(array_filter($gates, static fn (array $gate): bool => ! $gate['passed']));
$report = [
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'cooci_adoption_package_a=ready',
    'passed' => $failed === [],
    'gates' => $gates,
];
file_put_contents(
    $root.'/cooci-adoption-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
);

if ($failed !== []) {
    fwrite(STDERR, "Cooci adoption audit failed:\n");
    foreach ($failed as $item) {
        fwrite(STDERR, "- {$item['name']}: {$item['evidence']}\n");
    }
    exit(1);
}

echo 'Cooci adoption audit passed ('.count($gates)." gates).\n";
