<?php

$root = dirname(__DIR__);
$failures = [];
$files = [
    'provider' => $root.'/app/Providers/AppServiceProvider.php',
    'payment_manager' => $root.'/app/Services/Payments/PaymentProviderManager.php',
    'order_service' => $root.'/app/Services/Checkout/OrderService.php',
    'payment_service' => $root.'/app/Services/Payments/PaymentService.php',
    'review_service' => $root.'/app/Services/Reviews/ReviewService.php',
    'fixture' => $root.'/database/seeders/RostaAcceptanceSeeder.php',
    'fixture_command' => $root.'/app/Console/Commands/SeedAcceptanceFixtures.php',
    'composer' => $root.'/composer.json',
];

$sources = [];
foreach ($files as $name => $path) {
    $source = is_file($path) ? file_get_contents($path) : false;
    if ($source === false) {
        $failures[] = 'Missing or unreadable R3C2 backend contract file: '.$path;
        $source = '';
    }
    $sources[$name] = $source;
}

$required = [
    ['provider', "environment('testing')", 'Expanded OTP acceptance must remain testing-only.'],
    ['provider', "config('services.sms.driver') === 'acceptance'", 'Expanded OTP acceptance must require the dedicated driver.'],
    ['provider', 'otpRequestsPerMinute = $acceptanceOtp ? 12 : 3', 'Production OTP request throttling must remain three per minute.'],
    ['payment_manager', "'testing' => ! app()->environment('production')", 'Testing payments must remain forbidden in production.'],
    ['payment_manager', 'payment.testing_provider_forbidden', 'Testing payment production refusal must fail closed.'],
    ['order_service', 'order.idempotency_conflict', 'Order idempotency conflicts must remain explicit.'],
    ['order_service', 'checkout.quote_consumed', 'Consumed quotes must be rejected.'],
    ['order_service', 'checkout.catalog_changed', 'Stale catalog quotes must be rejected.'],
    ['payment_service', 'payment.idempotency_conflict', 'Payment idempotency conflicts must remain explicit.'],
    ['payment_service', 'ownedBy($user)', 'Payment verification must be owner-scoped.'],
    ['review_service', 'review.order_not_delivered', 'Reviews must require a delivered order.'],
    ['review_service', 'review.already_submitted', 'Duplicate reviews must be rejected.'],
    ['review_service', "'is_verified_purchase' => true", 'Accepted customer reviews must be verified purchases.'],
    ['fixture', 'foreign-acceptance-roastery', 'A deterministic unowned roastery must exist for seller IDOR acceptance.'],
    ['fixture_command', "'expected_seller_access' => 'denied'", 'The redacted fixture manifest must declare the expected denied scope.'],
    ['composer', 'audit:r3c2', 'Composer must expose the permanent R3C2 backend audit.'],
];

foreach ($required as [$file, $fragment, $message]) {
    if (! str_contains($sources[$file], $fragment)) {
        $failures[] = $message;
    }
}

$forbidden = [
    ['fixture', 'PaymentAttempt::query()->create'],
    ['fixture', 'Order::query()->create'],
    ['fixture', 'Review::query()->create'],
    ['provider', '$otpRequestsPerMinute = 12;'],
    ['payment_manager', "'testing' => true"],
];
foreach ($forbidden as [$file, $fragment]) {
    if (str_contains($sources[$file], $fragment)) {
        $failures[] = 'R3C2 backend contract contains forbidden fragment in '.str_replace($root.'/', '', $files[$file]).': '.$fragment;
    }
}

$report = [
    'passed' => $failures === [],
    'checked_files' => array_map(
        static fn (string $path): string => str_replace($root.'/', '', $path),
        $files,
    ),
    'failures' => $failures,
    'marker' => $failures === [] ? 'ROSTA_R3C2_BACKEND_CONTRACT_AUDITED' : null,
];

file_put_contents(
    $root.'/r3c2-commerce-roles-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
);

if ($failures !== []) {
    fwrite(STDERR, "R3C2 backend commerce and roles audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

fwrite(STDOUT, "R3C2 backend commerce and roles audit passed.\n");
