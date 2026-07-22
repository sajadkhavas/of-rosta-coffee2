<?php

$root = dirname(__DIR__);
$files = [
    'review_service' => file_get_contents($root.'/app/Services/Reviews/ReviewService.php'),
    'review_routes' => file_get_contents($root.'/routes/reviews-support.php'),
    'review_request' => file_get_contents($root.'/app/Http/Requests/Reviews/CreateReviewRequest.php'),
    'inquiry_service' => file_get_contents($root.'/app/Services/Support/InquiryService.php'),
    'inquiry_model' => file_get_contents($root.'/app/Models/Inquiry.php'),
    'inquiry_request' => file_get_contents($root.'/app/Http/Requests/Support/CreateInquiryRequest.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_07_22_210001_create_reviews_and_inquiries.php'),
    'contact' => file_get_contents(dirname($root).'/src/routes/contact.tsx'),
    'frontend_api' => file_get_contents(dirname($root).'/src/lib/api/inquiries.ts'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$gate(
    'verified_purchase_only',
    str_contains($files['review_service'], 'OrderStatus::Delivered')
        && str_contains($files['review_service'], "->where('user_id', \$user->id)")
        && str_contains($files['review_service'], "'is_verified_purchase' => true")
        && str_contains($files['migration'], "foreignUlid('order_item_id')->unique()"),
    'A customer may review one owned order item only after delivery.',
);

$gate(
    'moderation_before_publication',
    str_contains($files['review_service'], 'ReviewStatus::Approved->value')
        && str_contains($files['review_routes'], '/admin/reviews/{reviewId}')
        && str_contains($files['review_service'], "'status' => ReviewStatus::Pending"),
    'New reviews are pending and public queries return approved reviews only.',
);

$gate(
    'privacy_safe_public_review',
    str_contains($files['review_service'], "return 'خریدار رستا'")
        && str_contains($files['review_service'], "mb_substr(\$name, 0, 1).'***'")
        && ! str_contains($files['review_service'], "'mobile' => \$review->user"),
    'Public reviews must not expose customer mobile, email or full identity.',
);

$gate(
    'persisted_inquiry_success',
    str_contains($files['frontend_api'], 'apiFetch("/inquiries"')
        && str_contains($files['contact'], 'await createInquiry')
        && str_contains($files['contact'], 'receipt.referenceId')
        && ! str_contains($files['contact'], 'فرم تیکت آنلاین پس از اتصال'),
    'The contact page may show success only after receiving a persisted reference ID.',
);

$gate(
    'inquiry_abuse_boundaries',
    str_contains($files['inquiry_request'], "'website'")
        && str_contains($files['inquiry_request'], "'max:0'")
        && str_contains($files['review_routes'], 'throttle:inquiry-submit')
        && str_contains($files['inquiry_service'], "now()->subMinutes(10)"),
    'Inquiries require honeypot, rate limiting and a bounded duplicate window.',
);

$gate(
    'no_raw_ip_or_plain_contact',
    str_contains($files['inquiry_service'], "hash_hmac(")
        && str_contains($files['inquiry_model'], "'mobile' => 'encrypted'")
        && str_contains($files['inquiry_model'], "'email' => 'encrypted'")
        && str_contains($files['inquiry_model'], "'message' => 'encrypted'")
        && ! str_contains($files['migration'], "->ipAddress('ip')"),
    'Support records store encrypted contact/message data and an IP HMAC, never raw IP.',
);

$gate(
    'whole_bean_boundary',
    ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files)),
    'Reviews and support work must not introduce grind selection or grind state.',
);

$failed = array_values(array_filter($gates, static fn (array $item): bool => ! $item['passed']));
$report = [
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'reviews_support=ready',
    'passed' => $failed === [],
    'gates' => $gates,
];
file_put_contents(
    $root.'/reviews-support-audit.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
);

if ($failed !== []) {
    fwrite(STDERR, "Reviews/support audit failed:\n");
    foreach ($failed as $item) {
        fwrite(STDERR, "- {$item['name']}: {$item['evidence']}\n");
    }
    exit(1);
}

echo 'Reviews/support audit passed ('.count($gates)." gates).\n";
