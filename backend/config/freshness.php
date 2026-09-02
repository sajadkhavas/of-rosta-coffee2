<?php

$rawMaxAge = env('ROSTA_MAX_DISPATCH_ROAST_AGE_DAYS');

return [
    // Intentionally nullable: production must choose and accept the business freshness window.
    // Shipment is fail-closed while this value is missing or invalid.
    'max_dispatch_roast_age_days' => $rawMaxAge === null || trim((string) $rawMaxAge) === ''
        ? null
        : max(1, min(90, (int) $rawMaxAge)),
];
