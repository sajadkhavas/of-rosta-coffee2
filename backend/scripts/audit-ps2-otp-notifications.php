<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'client' => file_get_contents($root.'/app/Services/Kavenegar/KavenegarClient.php'),
    'otp_sender' => file_get_contents($root.'/app/Services/Sms/KavenegarOtpSender.php'),
    'log_sender' => file_get_contents($root.'/app/Services/Sms/LogOtpSender.php'),
    'job' => file_get_contents($root.'/app/Jobs/SendOtpCode.php'),
    'outbox' => file_get_contents($root.'/app/Services/Notifications/NotificationOutboxService.php'),
    'provider' => file_get_contents($root.'/app/Services/Notifications/Providers/KavenegarSmsProvider.php'),
    'readiness' => file_get_contents($root.'/app/Console/Commands/BackendReadiness.php'),
    'health' => file_get_contents($root.'/app/Http/Controllers/HealthController.php'),
    'config' => file_get_contents($root.'/config/rosta.php'),
    'env' => file_get_contents($root.'/.env.example'),
    'staging_env' => file_get_contents($root.'/.env.staging.example'),
    'openapi' => file_get_contents(dirname($root).'/docs/openapi/rosta-v1.yaml'),
    'tests' => file_get_contents($root.'/tests/Feature/KavenegarOtpProviderTest.php'),
];

$contains = static function (string $file, array $tokens) use ($files): bool {
    foreach ($tokens as $token) {
        if (! str_contains($files[$file], $token)) {
            return false;
        }
    }

    return true;
};

$gates = [
    'official_verify_lookup_contract' => $contains('client', [
        'verify/lookup.json',
        "'receptor'",
        "'token'",
        "'template'",
        "data_get(\$body, 'return.status')",
        "data_get(\$body, 'entries.0.messageid')",
    ]),
    'no_unsafe_inline_provider_retry' => ! str_contains($files['client'], '->retry(')
        && ! str_contains($files['provider'], '->retry('),
    'otp_unknown_outcome_is_at_most_once' => $contains('job', [
        'OtpDeliveryStatus::Processing',
        'OtpDeliveryStatus::Unknown',
        'worker_interrupted_outcome_unknown',
        'provider_accepted_persistence_unknown',
    ]),
    'outbox_unknown_outcome_is_dead_lettered' => $contains('outbox', [
        'stale_processing_outcome_unknown',
        'provider_accepted_persistence_unknown',
        '$providerFailure?->ambiguous === false',
    ]),
    'sensitive_values_are_not_logged' => ! preg_match('/[\'\"]code[\'\"]\s*=>\s*\$code/', $files['log_sender'])
        && ! preg_match('/[\'\"]message[\'\"]\s*=>\s*\$message/', file_get_contents($root.'/app/Services/Notifications/Providers/TestingSmsProvider.php'))
        && $contains('log_sender', ['mobile_suffix', 'Crypt::encryptString', 'CACHE_PREFIX']),
    'production_readiness_is_fail_closed' => $contains('readiness', [
        "'otp_activation'",
        "app()->environment('production') ? \$otpReady",
    ]) && $contains('health', ['otp_delivery', "app()->environment('production')"]),
    'configuration_is_explicit' => $contains('config', [
        'ROSTA_OTP_ENABLED',
        'KAVENEGAR_OTP_TEMPLATE_LOGIN',
        'KAVENEGAR_OTP_TEMPLATE_REGISTER',
        'KAVENEGAR_OTP_TEMPLATE_VERIFY_MOBILE',
        'KAVENEGAR_CIRCUIT_FAILURE_THRESHOLD',
    ]) && $contains('env', ['ROSTA_OTP_ENABLED=true', 'SMS_DRIVER=log'])
        && $contains('staging_env', ['ROSTA_OTP_ENABLED=false', 'SMS_DRIVER=disabled']),
    'provider_contract_tests_are_offline' => $contains('tests', [
        'Http::fake',
        'provider_rejection_is_terminal_and_redacted',
        'http_429_is_explicitly_retryable_without_an_inline_retry',
        'http_5xx_is_unknown_and_never_retried_inline',
        'connection_timeout_is_unknown_and_redacted',
        'malformed_success_response_is_unknown',
    ]),
    'openapi_exposes_unavailability' => $contains('openapi', [
        "'503': { \$ref: '#/components/responses/ServiceUnavailable' }",
        'OTP delivery is disabled, misconfigured, or temporarily unavailable',
    ]),
];

$failed = array_keys(array_filter($gates, static fn (bool $passed): bool => ! $passed));
if ($failed !== []) {
    fwrite(STDERR, 'PS2 OTP/notification audit failed: '.implode(', ', $failed).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'PS2 OTP/notification audit passed ('.count($gates).' gates).'.PHP_EOL);
