<?php

$root = dirname(__DIR__);
$files = [
    'service' => file_get_contents($root.'/app/Services/Catalog/MediaUploadService.php'),
    'routes' => file_get_contents($root.'/routes/media-uploads.php'),
    'create_request' => file_get_contents($root.'/app/Http/Requests/Catalog/CreateMediaUploadRequest.php'),
    'intent' => file_get_contents($root.'/app/Models/MediaUploadIntent.php'),
    'migration' => file_get_contents($root.'/database/migrations/2026_07_22_220001_create_media_upload_intents.php'),
    'filesystems' => file_get_contents($root.'/config/filesystems.php'),
    'config' => file_get_contents($root.'/config/rosta.php'),
    'console' => file_get_contents($root.'/routes/console.php'),
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
    str_contains($files['service'], "'roasteries/%s/uploads/%s/%s/%s.%s'")
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
    str_contains($files['service'], '->exists(')
        && str_contains($files['service'], '->size(')
        && str_contains($files['service'], '->mimeType(')
        && str_contains($files['service'], 'MediaRegistrationService'),
    'Completion must verify the stored object before creating a public media asset.',
);
$gate(
    'controlled_https_cdn',
    str_contains($files['service'], "config('rosta.media_uploads.public_base_url')")
        && str_contains($files['service'], "!== 'https'")
        && str_contains($files['service'], 'rawurlencode'),
    'Public URLs must be derived from a configured HTTPS CDN, never client input.',
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
        && str_contains($files['service'], 'expireDue')
        && str_contains($files['service'], '->delete('),
    'Expired intents and abandoned objects must be cleaned automatically.',
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
