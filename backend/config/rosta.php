<?php

$boolean = static fn (string $key, bool $default = false): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
);

$paymentEnabled = $boolean('ROSTA_PAYMENT_ENABLED');
$refundEnabled = $boolean('ROSTA_REFUND_ENABLED');
$smsEnabled = $boolean('ROSTA_SMS_ENABLED');
$mediaUploadsEnabled = $boolean('ROSTA_MEDIA_UPLOADS_ENABLED');
$zarinpalSandbox = $boolean('ZARINPAL_SANDBOX', true);

return [
    'api_version' => env('ROSTA_API_VERSION', 'v1'),
    'contract_version' => env('ROSTA_CONTRACT_VERSION', '2026-07-26-r5c'),
    'whole_bean_weights' => [50, 100, 250, 500, 1000],
    'single_roastery_orders' => false,
    'payment_enabled' => $paymentEnabled,
    'refund_enabled' => $refundEnabled,
    'sms_enabled' => $smsEnabled,
    'allowed_payment_redirect_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('ROSTA_ALLOWED_PAYMENT_REDIRECT_HOSTS', '')),
    ))),
    'payment' => [
        'enabled' => $paymentEnabled,
        'provider' => env('PAYMENT_DRIVER', 'disabled'),
        'currency' => 'IRR',
        'amount_multiplier' => max(1, (int) env('PAYMENT_AMOUNT_MULTIPLIER', 1)),
        'attempt_ttl_minutes' => max(1, min(60, (int) env('PAYMENT_ATTEMPT_TTL_MINUTES', 20))),
        'timeout_seconds' => max(2, min(60, (int) env('PAYMENT_TIMEOUT_SECONDS', 10))),
        'frontend_callback_url' => env('PAYMENT_CALLBACK_URL', 'http://localhost:5173/checkout'),
        'zarinpal' => [
            'merchant_id' => env('PAYMENT_MERCHANT_ID'),
            'sandbox' => $zarinpalSandbox,
            'request_url' => env('ZARINPAL_REQUEST_URL', $zarinpalSandbox ? 'https://sandbox.zarinpal.com/pg/v4/payment/request.json' : 'https://api.zarinpal.com/pg/v4/payment/request.json'),
            'verify_url' => env('ZARINPAL_VERIFY_URL', $zarinpalSandbox ? 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json' : 'https://api.zarinpal.com/pg/v4/payment/verify.json'),
            'start_pay_url' => env('ZARINPAL_START_PAY_URL', $zarinpalSandbox ? 'https://sandbox.zarinpal.com/pg/StartPay' : 'https://www.zarinpal.com/pg/StartPay'),
        ],
    ],
    'fulfillment' => [
        'preparation_sla_hours' => max(1, min(168, (int) env('ROSTA_PREPARATION_SLA_HOURS', 24))),
        'handoff_sla_hours' => max(1, min(336, (int) env('ROSTA_HANDOFF_SLA_HOURS', 48))),
    ],
    'settlement' => [
        'dispute_window_hours' => max(1, min(720, (int) env('ROSTA_DISPUTE_WINDOW_HOURS', 72))),
        'carrier_webhook_secret' => env('ROSTA_CARRIER_WEBHOOK_SECRET', ''),
    ],
    'refund' => [
        'enabled' => $refundEnabled,
        'provider' => env('REFUND_DRIVER', 'disabled'),
        'require_dual_control' => $boolean('ROSTA_REFUND_DUAL_CONTROL', true),
    ],
    'notifications' => [
        'enabled' => $smsEnabled,
        'sms_provider' => env('ORDER_SMS_PROVIDER', 'disabled'),
        'max_attempts' => max(1, min(20, (int) env('NOTIFICATION_MAX_ATTEMPTS', 5))),
        'retry_seconds' => max(30, min(3600, (int) env('NOTIFICATION_RETRY_SECONDS', 60))),
        'timeout_seconds' => max(1, min(60, (int) env('NOTIFICATION_TIMEOUT_SECONDS', 8))),
        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            'sender' => env('KAVENEGAR_ORDER_SENDER'),
            'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
        ],
    ],
    'media_uploads' => [
        'enabled' => $mediaUploadsEnabled,
        'disk' => env('ROSTA_MEDIA_UPLOAD_DISK', 's3'),
        'public_base_url' => env('ROSTA_MEDIA_PUBLIC_BASE_URL', ''),
        'max_size_bytes' => max(1_000_000, min(50_000_000, (int) env('ROSTA_MEDIA_MAX_SIZE_BYTES', 12_000_000))),
        'max_pixels' => max(1_000_000, min(100_000_000, (int) env('ROSTA_MEDIA_MAX_PIXELS', 40_000_000))),
        'max_width' => max(1000, min(20_000, (int) env('ROSTA_MEDIA_MAX_WIDTH', 8000))),
        'max_height' => max(1000, min(20_000, (int) env('ROSTA_MEDIA_MAX_HEIGHT', 8000))),
        'max_frames' => 1,
        'memory_limit_mb' => max(32, min(512, (int) env('ROSTA_MEDIA_MEMORY_LIMIT_MB', 128))),
        'processing_timeout_ms' => max(1000, min(60_000, (int) env('ROSTA_MEDIA_PROCESSING_TIMEOUT_MS', 15_000))),
        'max_processing_attempts' => max(1, min(5, (int) env('ROSTA_MEDIA_MAX_PROCESSING_ATTEMPTS', 3))),
        'orphan_retention_hours' => max(1, min(720, (int) env('ROSTA_MEDIA_ORPHAN_RETENTION_HOURS', 24))),
        'variant_version' => env('ROSTA_MEDIA_VARIANT_VERSION', 'v1'),
        'variant_widths' => array_values(array_filter(array_map(
            static fn (string $width): int => (int) trim($width),
            explode(',', (string) env('ROSTA_MEDIA_VARIANT_WIDTHS', '320,640,1280')),
        ))),
        'malware_policy' => 'decode_reencode',
        'ttl_minutes' => max(3, min(60, (int) env('ROSTA_MEDIA_UPLOAD_TTL_MINUTES', 15))),
    ],
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
    'catalog' => [
        'max_products_per_roastery' => max(1, min(10000, (int) env('ROSTA_MAX_PRODUCTS_PER_ROASTERY', 1000))),
        'max_media_per_roastery' => max(1, min(100000, (int) env('ROSTA_MAX_MEDIA_PER_ROASTERY', 10000))),
        'allowed_media_hosts' => array_values(array_unique(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) env('ROSTA_ALLOWED_MEDIA_HOSTS', '')),
        )))),
        'public_page_size' => 24,
        'seller_page_size' => 50,
    ],
    'checkout' => [
        'quote_ttl_minutes' => max(3, min(60, (int) env('ROSTA_QUOTE_TTL_MINUTES', 15))),
        'reservation_ttl_minutes' => max(5, min(120, (int) env('ROSTA_RESERVATION_TTL_MINUTES', 20))),
        'idempotency_ttl_hours' => max(1, min(168, (int) env('ROSTA_ORDER_IDEMPOTENCY_TTL_HOURS', 24))),
        'max_lines' => 100,
        'max_quantity_per_line' => 20,
    ],
];
