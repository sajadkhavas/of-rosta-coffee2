<?php

$root = dirname(__DIR__);
$failures = [];

$files = [
    'sender' => $root.'/app/Services/Sms/AcceptanceOtpSender.php',
    'command' => $root.'/app/Console/Commands/ConsumeAcceptanceOtp.php',
    'provider' => $root.'/app/Providers/AppServiceProvider.php',
    'test' => $root.'/tests/Feature/AcceptanceOtpBridgeTest.php',
    'composer' => $root.'/composer.json',
];

$sources = [];
foreach ($files as $name => $path) {
    $source = is_file($path) ? file_get_contents($path) : false;
    if ($source === false) {
        $failures[] = 'Missing or unreadable R3C browser bridge file: '.$path;
        $source = '';
    }
    $sources[$name] = $source;
}

$required = [
    ['sender', "environment('testing')", 'Acceptance OTP sender must be restricted to testing.'],
    ['sender', "config('services.sms.driver') === 'acceptance'", 'Acceptance OTP sender must require its dedicated driver.'],
    ['sender', 'Crypt::encryptString($code)', 'Acceptance OTP must be encrypted before cache storage.'],
    ['sender', 'now()->addMinutes(3)', 'Acceptance OTP storage must have a bounded lifetime.'],
    ['command', 'Cache::pull(', 'Acceptance OTP consumption must be destructive and one-time.'],
    ['command', "preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i'", 'Acceptance OTP command must validate the challenge ULID.'],
    ['command', "preg_match('/^\\d{6}$/'", 'Acceptance OTP command must validate the decrypted six-digit payload.'],
    ['provider', "driver === 'acceptance'", 'The service provider must explicitly bind the acceptance driver.'],
    ['provider', "environment('testing')", 'The service provider must restrict the acceptance binding to testing.'],
    ['test', 'encrypted_at_rest_and_consumed_once', 'Tests must prove encryption and one-time consumption.'],
    ['test', 'forbidden_outside_testing', 'Tests must prove production refusal.'],
    ['composer', 'audit:browser-acceptance', 'Composer must expose the permanent browser acceptance audit.'],
];

foreach ($required as [$file, $fragment, $message]) {
    if (! str_contains($sources[$file], $fragment)) {
        $failures[] = $message;
    }
}

$routeSources = '';
foreach (glob($root.'/routes/*.php') ?: [] as $routeFile) {
    $routeSources .= (string) file_get_contents($routeFile);
}
if (str_contains($routeSources, 'acceptance-otp') || str_contains($routeSources, 'AcceptanceOtp')) {
    $failures[] = 'Acceptance OTP retrieval must never be exposed through an HTTP route.';
}

foreach (['fixed_otp', 'otp_code', "'000000'", 'Route::'] as $forbidden) {
    if (str_contains($sources['sender'].$sources['command'], $forbidden)) {
        $failures[] = 'Browser acceptance OTP bridge contains forbidden fragment: '.$forbidden;
    }
}

$report = [
    'passed' => $failures === [],
    'checked_files' => array_map(
        static fn (string $path): string => str_replace($root.'/', '', $path),
        $files,
    ),
    'http_retrieval_exposed' => false,
    'failures' => $failures,
    'marker' => $failures === [] ? 'ROSTA_R3C_BROWSER_BRIDGE_AUDITED' : null,
];

file_put_contents(
    $root.'/browser-acceptance-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
);

if ($failures !== []) {
    fwrite(STDERR, "Browser acceptance contract audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }

    exit(1);
}

fwrite(STDOUT, "Browser acceptance contract audit passed.\n");
