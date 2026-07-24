<?php

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relative) use ($root, &$failures): string {
    $path = $root.'/'.$relative;
    $content = is_file($path) ? file_get_contents($path) : false;
    if ($content === false) {
        $failures[] = 'Missing or unreadable file: '.$relative;

        return '';
    }

    return $content;
};

$require = static function (
    string $relative,
    string $needle,
    string $message,
) use ($read, &$failures): void {
    if (! str_contains($read($relative), $needle)) {
        $failures[] = $message;
    }
};

$migration = $read(
    'database/migrations/2026_07_22_010001_create_content_seo_tables.php',
);
foreach (
    ['content_authors', 'content_entries', 'content_relations', 'seo_redirects'] as $table
) {
    if (! str_contains($migration, "Schema::create('{$table}'")) {
        $failures[] = 'SEO/content table missing: '.$table;
    }
}

if (preg_match('/(?:raw_html|body_html|html_content)/i', $migration) === 1) {
    $failures[] = 'Raw HTML storage is forbidden in the structured content schema.';
}

$require(
    'app/Services/Content/ContentBlockValidator.php',
    "'product_grid'",
    'Structured content must support product relationships without raw HTML.',
);
$require(
    'app/Services/Content/ContentBlockValidator.php',
    "'faq'",
    'Structured FAQ blocks are required for controlled schema output.',
);
$require(
    'app/Services/Content/ContentWriteService.php',
    'returned_to_review',
    'Published content edits must return to review.',
);
$require(
    'app/Services/Content/ContentWriteService.php',
    'content.edit_conflict',
    'Concurrent editorial updates must fail instead of silently overwriting data.',
);
$require(
    'app/Http/Requests/Content/UpsertContentEntryRequest.php',
    "'expected_content_hash'",
    'Content updates must require an optimistic-lock hash.',
);
$require(
    'app/Services/Content/ContentPublicationService.php',
    'content.review_required',
    'Draft content must not bypass the review state.',
);
$require(
    'app/Services/Content/ContentPublicationService.php',
    'content.author_required',
    'Publishing must require an active author.',
);
$require(
    'app/Services/Content/ContentPublicationService.php',
    "'reviewed_by' => \$reviewer->id",
    'Publishing must record the reviewer.',
);
$require(
    'app/Models/ContentEntry.php',
    "->where('robots_index', true)",
    'Indexable content scope must require robots_index.',
);
$contentEntryModel = $read('app/Models/ContentEntry.php');
if (
    ! str_contains($contentEntryModel, '->published()')
    && ! str_contains($contentEntryModel, 'scopePublished($query)')
) {
    $failures[] = 'Indexability must remain dependent on publication status.';
}
$require(
    'app/Support/SeoPath.php',
    "'/checkout'",
    'Transactional routes must be reserved from public SEO pages.',
);
$require(
    'app/Support/SeoPath.php',
    'rawurldecode(',
    'Canonical paths must reject encoded traversal attempts.',
);
$require(
    'app/Services/Content/SeoRedirectService.php',
    "'source_path' => SeoPath::assertPublic(",
    'Redirect sources must not overlap private or transactional routes.',
);
$require(
    'app/Services/Content/SeoRedirectService.php',
    'seo.redirect_loop',
    'Redirect loops must be rejected.',
);
$require(
    'app/Services/Content/SeoRedirectService.php',
    'seo.redirect_chain_too_long',
    'Long redirect chains must be rejected.',
);

foreach ([
    'broken_relations',
    'orphaned_entries',
    'weak_outbound_entries',
    'MAX_RELATIONS',
] as $needle) {
    $require(
        'app/Services/Content/ContentLinkReportService.php',
        $needle,
        'Content link report is missing required boundary: '.$needle,
    );
}
$require(
    'app/Http/Controllers/Admin/AdminContentLinkReportController.php',
    'ContentLinkReportService',
    'Internal-link health must be exposed through an administrator controller.',
);

$require(
    'routes/api.php',
    "Route::get('/seo/indexable'",
    'An authoritative indexable URL feed is required.',
);
$require(
    'routes/api.php',
    "Route::patch('/content/{entryId}/status'",
    'Content publication must use a dedicated admin status boundary.',
);
$require(
    'routes/api.php',
    "Route::post('/seo-redirects'",
    'SEO redirects must be managed through administrator routes.',
);
$require(
    'routes/api.php',
    "Route::get('/content-link-report'",
    'Administrator content operations must expose internal-link health.',
);

if ($failures !== []) {
    fwrite(STDERR, "SEO/content contract audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

fwrite(STDOUT, "SEO/content contract audit passed.\n");
