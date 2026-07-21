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
    'otp' => [
        'length' => 6,
        'ttl_seconds' => max(30, min(900, (int) env('ROSTA_OTP_TTL_SECONDS', 120))),
        'resend_after_seconds' => max(15, min(900, (int) env('ROSTA_OTP_RESEND_AFTER_SECONDS', 60))),
        'max_attempts' => max(3, min(10, (int) env('ROSTA_OTP_MAX_ATTEMPTS', 5))),
    ],
    'auth_sessions' => [
        'ttl_minutes' => max(30, min(43_200, (int) env('ROSTA_AUTH_SESSION_TTL_MINUTES', 120))),
        'max_active_per_user' => max(1, min(20, (int) env('ROSTA_MAX_ACTIVE_SESSIONS', 5))),
    ],
    'addresses' => [
        'max_per_user' => max(1, min(100, (int) env('ROSTA_MAX_ADDRESSES_PER_USER', 20))),
    ],
];
