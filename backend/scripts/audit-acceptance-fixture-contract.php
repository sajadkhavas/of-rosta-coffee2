<?php

$root = dirname(__DIR__);
$failures = [];

$files = [
    'seeder' => $root.'/database/seeders/RostaAcceptanceSeeder.php',
    'command' => $root.'/app/Console/Commands/SeedAcceptanceFixtures.php',
    'test' => $root.'/tests/Feature/AcceptanceFixtureCommandTest.php',
    'composer' => $root.'/composer.json',
];

$sources = [];
foreach ($files as $name => $path) {
    $source = is_file($path) ? file_get_contents($path) : false;
    if ($source === false) {
        $failures[] = 'Missing or unreadable acceptance fixture '.$name.': '.$path;
        $source = '';
    }
    $sources[$name] = $source;
}

$requiredFragments = [
    ['seeder', "environment(['local', 'testing'])", 'Seeder must be restricted to local/testing.'],
    ['seeder', 'require an empty migrated database', 'Seeder must require an empty database.'],
    ['seeder', 'DatabaseSeeder::class', 'Seeder must extend the canonical development fixture base.'],
    ['seeder', "'weight' => 100", 'Acceptance catalog must include the allowed 100g variant.'],
    ['seeder', "'weight' => 500", 'Acceptance catalog must include the allowed 500g variant.'],
    ['command', 'ROSTA_R3A_ACCEPTANCE_FIXTURES_COMPLETE', 'Command must emit the R3A marker.'],
    ['command', "'alias' => 'acceptance-customer'", 'Manifest must use a non-sensitive customer alias.'],
    ['command', "'alias' => 'acceptance-administrator'", 'Manifest must use a non-sensitive administrator alias.'],
    ['command', "'alias' => 'acceptance-seller'", 'Manifest must use a non-sensitive seller alias.'],
    ['test', 'forbidden_in_production', 'Tests must prove production refusal.'],
    ['test', 'refuse_duplicate_execution', 'Tests must prove duplicate refusal.'],
    ['composer', 'audit:acceptance-fixtures', 'Composer must expose the permanent R3A audit.'],
];

foreach ($requiredFragments as [$file, $fragment, $message]) {
    if (! str_contains($sources[$file], $fragment)) {
        $failures[] = $message;
    }
}

foreach (['migrate:fresh', 'key:generate', 'fixed_otp', 'otp_code'] as $forbidden) {
    if (str_contains($sources['seeder'].$sources['command'], $forbidden)) {
        $failures[] = 'Acceptance fixture implementation contains forbidden fragment: '.$forbidden;
    }
}

$allowedWeights = [50, 100, 250, 500, 1000];
preg_match_all("/'weight'\s*=>\s*(\d+)/", $sources['seeder'], $weightMatches);
foreach ($weightMatches[1] as $weight) {
    if (! in_array((int) $weight, $allowedWeights, true)) {
        $failures[] = 'Acceptance fixture contains unsupported weight: '.$weight.'g';
    }
}

$report = [
    'passed' => $failures === [],
    'checked_files' => array_map(
        static fn (string $path): string => str_replace($root.'/', '', $path),
        $files,
    ),
    'allowed_weights' => $allowedWeights,
    'failures' => $failures,
    'marker' => $failures === [] ? 'ROSTA_R3A_ACCEPTANCE_FIXTURE_CONTRACT_AUDITED' : null,
];

file_put_contents(
    $root.'/acceptance-fixture-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
);

if ($failures !== []) {
    fwrite(STDERR, "Acceptance fixture contract audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }

    exit(1);
}

fwrite(STDOUT, "Acceptance fixture contract audit passed.\n");
