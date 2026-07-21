<?php

return [
    'api_version' => env('ROSTA_API_VERSION', 'v1'),
    'contract_version' => env('ROSTA_CONTRACT_VERSION', '2026-07-21-phase-6'),
    'whole_bean_weights' => [50, 100, 250, 500, 1000],
    'single_roastery_orders' => true,
    'payment_enabled' => (bool) env('ROSTA_PAYMENT_ENABLED', false),
    'sms_enabled' => (bool) env('ROSTA_SMS_ENABLED', false),
    'allowed_payment_redirect_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('ROSTA_ALLOWED_PAYMENT_REDIRECT_HOSTS', '')),
    ))),
];
