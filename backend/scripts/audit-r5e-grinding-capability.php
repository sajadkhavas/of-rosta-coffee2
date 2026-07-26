<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'migration' => $root.'/database/migrations/2026_07_27_000001_seed_r5e_grinding_profiles.php',
    'capability_model' => $root.'/app/Models/RoasteryGrindingCapability.php',
    'profile_model' => $root.'/app/Models/GrindingProfile.php',
    'policy' => $root.'/app/Services/Catalog/RoasteryGrindingPolicy.php',
    'request' => $root.'/app/Http/Requests/Catalog/UpsertGrindingCapabilityRequest.php',
    'seller_controller' => $root.'/app/Http/Controllers/Seller/SellerGrindingCapabilityController.php',
    'public_controller' => $root.'/app/Http/Controllers/Catalog/RoasteryGrindingCapabilityController.php',
    'routes' => $root.'/routes/seller-bootstrap.php',
    'resource' => $root.'/app/Http/Resources/GrindingCapabilityResource.php',
    'test' => $root.'/tests/Feature/R5EGrindingCapabilityTest.php',
    'composer' => $root.'/composer.json',
    'contract' => dirname($root).'/docs/r5/R5E_ROASTERY_GRINDING_CAPABILITY.md',
    'quote' => $root.'/app/Services/Checkout/QuoteService.php',
];

$sources = [];
foreach ($files as $key => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "R5E audit missing {$path}\n");
        exit(1);
    }

    $sources[$key] = file_get_contents($path);
}

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};
$hasAll = static function (string $source, array $fragments): bool {
    foreach ($fragments as $fragment) {
        if (! str_contains($source, $fragment)) {
            return false;
        }
    }

    return true;
};

$composer = json_decode($sources['composer'], true, flags: JSON_THROW_ON_ERROR);
$scripts = $composer['scripts'] ?? [];

$gate(
    'permanent_backend_gate',
    ($scripts['audit:r5e'] ?? null) === '@php scripts/audit-r5e-grinding-capability.php'
        && in_array('@audit:r5e', $scripts['check'] ?? [], true),
    'composer check must permanently execute the R5E audit.',
);

$gate(
    'approved_profile_catalogue',
    $hasAll($sources['migration'], [
        "'turkish'",
        "'home-espresso-pressurised'",
        "'moka-pot'",
        "'aeropress'",
        "'v60'",
        "'chemex'",
        "'filter-machine'",
        "'french-press'",
        "'cold-brew'",
        "'customer_editable' => false",
    ]),
    'The nine approved profiles must be versioned and operations-owned.',
);

$gate(
    'authoritative_capability_policy',
    $hasAll($sources['capability_model'], [
        'GrindingAvailability::class',
        'FeeMode::class',
        "'supported_weights' => 'array'",
    ]) && $hasAll($sources['policy'], [
        'fee_mode',
        'fee_amount',
        'supported_weights',
        'grinding_profile_ids',
        'profiles()->sync',
        'ValidationException::withMessages',
    ]),
    'Laravel must own fee, profile, weight, capacity and availability invariants.',
);

$gate(
    'seller_scope_and_audit',
    $hasAll($sources['seller_controller'], [
        'assertRoasteryAccess',
        'Role::RoasteryOwner',
        'Role::RoasteryManager',
        'catalog.roastery_grinding_capability.updated',
    ]) && $hasAll($sources['routes'], [
        '/grinding-capability',
        'SellerGrindingCapabilityController',
        '/grinding-profiles',
    ]),
    'Capability reads and writes must be authenticated, scoped and audited.',
);

$gate(
    'public_capability',
    $hasAll($sources['resource'], [
        "'is_available'",
        "'fee_amount'",
        "'supported_weights'",
        "'profiles'",
        'آسیاب روستری رایگان',
    ]) && $hasAll($sources['public_controller'], [
        "where('status', 'verified')",
        "where('is_active', true)",
        'GrindingCapabilityResource',
        "'profiles' => static fn",
    ]) && $hasAll($sources['routes'], [
        '/roasteries/{roasterySlug}/grinding-capability',
        'RoasteryGrindingCapabilityController',
    ]),
    'A dedicated public endpoint must expose only an active capability for a verified roastery.',
);

$gate(
    'whole_bean_boundary',
    ! str_contains($sources['quote'], 'grinding_profile_ids')
        && ! str_contains($sources['quote'], "'service_type' => 'grinding'")
        && $hasAll($sources['contract'], [
            'R5E does not attach grinding to cart, quote or order items',
            'no grinding variant, SKU or stock dimension is introduced',
            'ROSTA_R5E_GRINDING_CAPABILITY_COMPLETE',
        ]),
    'R5E must publish capability without turning grinding into inventory or checkout state.',
);

$gate(
    'feature_coverage',
    $hasAll($sources['test'], [
        'assertCount(9, $profiles)',
        'test_owner_can_publish_a_scoped_free_grinding_capability',
        'test_fixed_grinding_requires_positive_money',
        'assertNotFound()',
        "'/grinding-capability'",
        "'action' => 'catalog.roastery_grinding_capability.updated'",
    ]),
    'Feature tests must cover catalogue, scope, money normalization, audit and public publication.',
);

$failed = array_values(array_filter(
    $gates,
    static fn (array $item): bool => ! $item['passed'],
));
$report = [
    'generated_at' => (new DateTimeImmutable)->format(DATE_ATOM),
    'passed' => $failed === [],
    'checked_files' => array_values($files),
    'gates' => $gates,
    'failures' => array_column($failed, 'name'),
    'marker' => $failed === [] ? 'ROSTA_R5E_GRINDING_CAPABILITY_COMPLETE' : null,
];

file_put_contents(
    $root.'/r5e-grinding-capability-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
);

if ($failed !== []) {
    fwrite(STDERR, "R5E grinding capability audit failed:\n");
    foreach ($failed as $failure) {
        fwrite(STDERR, "- {$failure['name']}: {$failure['evidence']}\n");
    }
    exit(1);
}

fwrite(STDOUT, 'R5E grinding capability audit passed ('.count($gates)." gates).\n");
fwrite(STDOUT, "ROSTA_R5E_GRINDING_CAPABILITY_COMPLETE\n");
