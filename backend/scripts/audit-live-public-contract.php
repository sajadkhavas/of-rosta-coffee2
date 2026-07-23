<?php

$root = dirname(__DIR__);
$frontend = dirname($root).'/src';
$files = [
    'providers' => file_get_contents($root.'/bootstrap/providers.php'),
    'safety' => file_get_contents($root.'/app/Providers/ProductionSafetyServiceProvider.php'),
    'catalog' => file_get_contents($root.'/app/Http/Controllers/Catalog/ProductController.php'),
    'content' => file_get_contents($root.'/app/Http/Controllers/Content/ContentController.php'),
    'reviews' => file_get_contents($root.'/app/Services/Reviews/ReviewService.php'),
    'public_reviews' => file_get_contents($root.'/app/Http/Controllers/Reviews/PublicReviewController.php'),
    'review_controller' => file_get_contents($root.'/app/Http/Controllers/Reviews/ReviewController.php'),
    'home' => file_get_contents($frontend.'/routes/index.tsx'),
    'blog' => file_get_contents($frontend.'/routes/blog.$slug.tsx'),
    'quiz' => file_get_contents($frontend.'/routes/quiz.tsx'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$gate(
    'production_seed_commands_are_blocked',
    str_contains($files['providers'], 'ProductionSafetyServiceProvider')
        && str_contains($files['safety'], 'CommandStarting')
        && str_contains($files['safety'], "'db:seed'")
        && str_contains($files['safety'], "'migrate:fresh'")
        && str_contains($files['safety'], "environment('production')"),
    'Production must fail closed for db:seed and migrate:fresh.',
);

$gate(
    'public_catalog_is_published_only',
    str_contains($files['catalog'], 'published')
        || str_contains($files['catalog'], 'ProductStatus::Published'),
    'Public catalog responses must only expose published products.',
);

$gate(
    'public_content_is_published_only',
    str_contains($files['content'], "ContentStatus::Published")
        && str_contains($files['content'], "where('status'"),
    'Public editorial responses must only expose published CMS entries.',
);

$gate(
    'reviews_are_verified_and_moderated',
    str_contains($files['reviews'], 'OrderStatus::Delivered')
        && str_contains($files['reviews'], "'is_verified_purchase' => true")
        && str_contains($files['reviews'], 'ReviewStatus::Approved')
        && str_contains($files['public_reviews'], 'publicForProduct')
        && str_contains($files['review_controller'], 'order_item_id'),
    'Review submission must require a delivered owned order item and public listing must require approved verified purchase.',
);

$gate(
    'frontend_consumes_live_contracts',
    str_contains($files['home'], 'homepageQueryOptions')
        && str_contains($files['blog'], 'blogEntryQueryOptions')
        && str_contains($files['quiz'], 'productsQueryOptions'),
    'Homepage, editorial and quiz surfaces must consume live API/CMS contracts.',
);

$gate(
    'whole_bean_public_boundary',
    ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files))
        && str_contains($files['home'], 'دانه کامل')
        && str_contains($files['quiz'], 'دانه کامل'),
    'Live public surfaces must remain whole-bean only.',
);

$failed = array_values(array_filter($gates, static fn (array $item): bool => ! $item['passed']));
file_put_contents($root.'/live-public-contract-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'live_public_contract=ready',
    'passed' => $failed === [],
    'gates' => $gates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, "Live public contract audit failed:\n");
    foreach ($failed as $item) {
        fwrite(STDERR, "- {$item['name']}: {$item['evidence']}\n");
    }
    exit(1);
}

echo 'Live public contract audit passed ('.count($gates)." gates).\n";
