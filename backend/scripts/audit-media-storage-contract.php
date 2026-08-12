<?php

$root = dirname(__DIR__);
$files = [
    'service' => file_get_contents($root.'/app/Services/Catalog/MediaUploadService.php'),
    'processor' => file_get_contents($root.'/app/Services/Media/SecureImageProcessor.php'),
    'job' => file_get_contents($root.'/app/Jobs/ProcessMediaUpload.php'),
    'controller' => file_get_contents($root.'/app/Http/Controllers/Seller/SellerMediaController.php'),
    'routes' => file_get_contents($root.'/routes/media-uploads.php'),
    'create_request' => file_get_contents($root.'/app/Http/Requests/Catalog/CreateMediaUploadRequest.php'),
    'intent' => file_get_contents($root.'/app/Models/MediaUploadIntent.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_07_22_220001_create_media_upload_intents.php'),
    'processing_migration' => file_get_contents($root.'/database/migrations/2026_08_12_000002_add_secure_processing_to_media_uploads.php'),
    'filesystems' => file_get_contents($root.'/config/filesystems.php'),
    'config' => file_get_contents($root.'/config/rosta.php'),
    'console' => file_get_contents($root.'/routes/console.php'),
    'tests' => file_get_contents($root.'/tests/Feature/MediaUploadCompletionTest.php')
        .file_get_contents($root.'/tests/Feature/SecureImageProcessorTest.php'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$gate(
    'roastery_scoped_uploads',
    str_contains($files['routes'], '/seller/roasteries/{roasteryId}/media/uploads')
        && str_contains($files['service'], "->where('roastery_id', \$roastery->id)")
        && str_contains($files['service'], "->where('user_id', \$actor->id)"),
    'Create and completion operations must remain authenticated and roastery/user scoped.',
);
$gate(
    'backend_owned_object_keys',
    str_contains($files['service'], "'_private/roasteries/%s/uploads/%s/%s/%s.%s'")
        && ! str_contains($files['create_request'], "'object_key'"),
    'Clients may never choose object keys or overwrite arbitrary storage objects.',
);
$gate(
    'bounded_media_contract',
    str_contains($files['create_request'], "Rule::in(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])")
        && str_contains($files['create_request'], 'checksum_sha256')
        && str_contains($files['create_request'], 'size_bytes')
        && str_contains($files['config'], 'ROSTA_MEDIA_MAX_SIZE_BYTES'),
    'Upload intents require a bounded MIME, exact size and SHA-256 checksum.',
);
$gate(
    'signed_checksum_upload',
    str_contains($files['service'], 'temporaryUploadUrl')
        && str_contains($files['service'], "'ChecksumSHA256'")
        && str_contains($files['service'], "'ContentType'"),
    'The signed PUT must bind content type and checksum at the object store boundary.',
);
$gate(
    'completion_revalidates_object',
    str_contains($files['service'], '->readStream(')
        && str_contains($files['service'], "hash_init('sha256')")
        && str_contains($files['service'], 'SecureImageProcessor')
        && str_contains($files['processor'], 'pingImageBlob')
        && str_contains($files['processor'], 'readImageBlob'),
    'The worker must derive size, checksum, magic MIME and decode truth from stored bytes.',
);
$gate(
    'controlled_https_cdn',
    str_contains($files['service'], "config('rosta.media_uploads.public_base_url')")
        && str_contains($files['service'], "!== 'https'")
        && str_contains($files['service'], "'published/roasteries/%s/media/%s/%s/%d.%s'")
        && str_contains($files['service'], "'status' => MediaUploadStatus::Ready"),
    'Public URLs may only be derived for sanitized, versioned variants finalized as Ready.',
);
$gate(
    'fail_closed_activation',
    str_contains($files['config'], "'enabled' => \$mediaUploadsEnabled")
        && str_contains($files['service'], 'catalog.media_storage_unavailable')
        && str_contains($files['filesystems'], "'throw' => true"),
    'Media uploads remain disabled until storage and CDN configuration are complete.',
);
$gate(
    'abandoned_upload_cleanup',
    str_contains($files['console'], 'media:expire-upload-intents')
        && str_contains($files['console'], 'media:cleanup-terminal')
        && str_contains($files['service'], 'expireDue')
        && str_contains($files['service'], 'cleanupTerminal')
        && str_contains($files['service'], '->delete('),
    'Expired intents and abandoned objects must be cleaned automatically.',
);
$gate(
    'bounded_decode_and_metadata_policy',
    str_contains($files['processor'], 'RESOURCETYPE_MEMORY')
        && str_contains($files['processor'], 'image_dimensions_exceeded')
        && str_contains($files['processor'], 'animated_image_rejected')
        && str_contains($files['processor'], 'autoOrient')
        && str_contains($files['processor'], 'stripImage')
        && str_contains($files['config'], "'malware_policy' => 'decode_reencode'"),
    'Decode limits, single-frame policy, EXIF orientation and metadata stripping are mandatory.',
);
$gate(
    'responsive_versioned_variants',
    str_contains($files['processor'], "'format' => 'jpeg'")
        && str_contains($files['processor'], "'format' => 'webp'")
        && str_contains($files['processor'], "'format' => 'avif'")
        && str_contains($files['config'], 'ROSTA_MEDIA_VARIANT_WIDTHS')
        && str_contains($files['config'], 'ROSTA_MEDIA_VARIANT_VERSION'),
    'The pipeline must produce fallback, WebP and AVIF srcset candidates under a versioned key.',
);
$gate(
    'durable_state_and_explicit_retry',
    str_contains($files['processing_migration'], "'processing_started_at'")
        && str_contains($files['service'], 'MediaUploadStatus::Processing')
        && str_contains($files['service'], 'MediaUploadStatus::Ready')
        && str_contains($files['service'], 'MediaUploadStatus::Rejected')
        && str_contains($files['service'], 'failure_retryable')
        && str_contains($files['job'], 'public int $tries = 1')
        && str_contains($files['routes'], '/retry'),
    'Processing state, terminal rejection and bounded owner-requested retry must be durable.',
);
$gate(
    'legacy_public_registration_disabled',
    str_contains($files['controller'], 'catalog.media_direct_registration_disabled')
        && str_contains($files['controller'], 'Upload Intent'),
    'Client-authored public sources and dimensions may not bypass the secure pipeline.',
);
$gate(
    'hostile_fixture_matrix',
    str_contains($files['tests'], 'checksum_mismatch')
        && str_contains($files['tests'], 'spoofed_mime')
        && str_contains($files['tests'], 'truncated_image')
        && str_contains($files['tests'], 'oversized_pixel_count')
        && str_contains($files['tests'], 'animated_webp')
        && str_contains($files['tests'], 'exif_orientation')
        && str_contains($files['tests'], 'cannot_complete_or_read')
        && str_contains($files['tests'], 'can_be_retried_explicitly'),
    'Offline hostile fixtures must cover spoof, corruption, bombs, animation, EXIF, IDOR and retry.',
);
$gate(
    'whole_bean_boundary',
    ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files)),
    'Media work must not introduce grind selection or grind state.',
);

$failed = array_values(array_filter($gates, static fn (array $item): bool => ! $item['passed']));
file_put_contents($root.'/media-storage-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'media_storage=ready',
    'passed' => $failed === [],
    'gates' => $gates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, "Media storage audit failed:\n");
    foreach ($failed as $item) {
        fwrite(STDERR, "- {$item['name']}: {$item['evidence']}\n");
    }
    exit(1);
}

echo 'Media storage audit passed ('.count($gates)." gates).\n";
