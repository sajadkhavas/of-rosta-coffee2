<?php

$frontendOrigin = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [$frontendOrigin],
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
