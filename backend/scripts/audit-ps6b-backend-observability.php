<?php

$root = dirname(__DIR__);
$failures = [];

$contracts = [
    'bootstrap/providers.php' => [
        'ObservabilityServiceProvider::class',
    ],
    'app/Providers/ObservabilityServiceProvider.php' => [
        'Queue::before',
        'Queue::after',
        'Queue::failing',
        'QueueTelemetry::class',
    ],
    'app/Services/Observability/QueueTelemetry.php' => [
        'queue.processing',
        'queue.processed',
        'queue.failed',
        'duration_ms',
        'exception_class',
    ],
    'app/Services/Observability/QueueRuntimeHealth.php' => [
        'failed_jobs_table',
        'oldest_failed_age_seconds',
        'max_queue_depth',
        "driver === 'sync'",
        "driver === 'redis'",
    ],
    'app/Support/OperationalContextRedactor.php' => [
        'authorization',
        'password',
        'secret',
        'token',
        'payload',
        'Bearer [redacted]',
    ],
    'app/Http/Controllers/HealthController.php' => [
        "'queue_runtime' => false",
        "snapshot()['ready']",
    ],
    'config/queue.php' => [
        "'retry_after'",
        "'after_commit' => true",
        "'database-uuids'",
        "'failed_jobs'",
    ],
    'config/observability.php' => [
        'ROSTA_OBSERVABILITY_QUEUES',
        'ROSTA_MAX_FAILED_JOBS',
        'ROSTA_MAX_FAILED_JOB_AGE_SECONDS',
        'ROSTA_MAX_QUEUE_DEPTH',
    ],
    'tests/Feature/PS6BBackendObservabilityTest.php' => [
        'bounded query budget',
        'must-not-leak',
        'fail_closed',
    ],
];

foreach ($contracts as $path => $needles) {
    $source = @file_get_contents($root.'/'.$path);
    if ($source === false) {
        $failures[] = 'Missing PS6B contract file: '.$path;
        continue;
    }

    foreach ($needles as $needle) {
        if (! str_contains($source, $needle)) {
            $failures[] = sprintf('Missing PS6B marker [%s] in %s', $needle, $path);
        }
    }
}

$health = @file_get_contents($root.'/app/Services/Observability/QueueRuntimeHealth.php') ?: '';
foreach (['payload', 'exception'] as $forbidden) {
    if (preg_match('/[\'\"]'.$forbidden.'[\'\"]\s*=>/', $health) === 1) {
        $failures[] = 'Queue health snapshot must never expose '.$forbidden.'.';
    }
}

$telemetry = @file_get_contents($root.'/app/Services/Observability/QueueTelemetry.php') ?: '';
if (str_contains($telemetry, 'getRawBody') || str_contains($telemetry, 'getRawPayload')) {
    $failures[] = 'Queue telemetry must never read serialized job payloads.';
}

if ($failures !== []) {
    fwrite(STDERR, "PS6B backend observability audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

fwrite(STDOUT, "PS6B backend observability audit passed.\n");
