<?php

$allowedOrigins = array_values(array_unique(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env(
        'FRONTEND_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173',
    )),
))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'X-Request-ID',
        'Idempotency-Key',
    ],
    'exposed_headers' => [
        'X-Request-ID',
        'X-Rosta-Contract-Version',
        'Retry-After',
    ],
    'max_age' => 600,
    'supports_credentials' => true,
];
