<?php

$root = dirname(__DIR__);
$repo = dirname($root);
$files = [
    'command' => file_get_contents($root.'/app/Console/Commands/StagingAcceptance.php'),
    'readiness' => file_get_contents($root.'/app/Console/Commands/BackendReadiness.php'),
    'environment' => file_get_contents($root.'/.env.staging.example'),
    'compose' => file_get_contents($repo.'/deploy/staging/docker-compose.yml'),
    'entrypoint' => file_get_contents($root.'/docker/entrypoint.sh'),
    'deploy' => file_get_contents($repo.'/deploy/staging/deploy.sh'),
    'deploy_workflow' => file_get_contents($repo.'/.github/workflows/staging-deploy.yml'),
    'acceptance' => file_get_contents($repo.'/deploy/staging/acceptance.sh'),
    'rollback' => file_get_contents($repo.'/deploy/staging/rollback.sh'),
];

$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};
$containsAll = static function (string $content, array $tokens): bool {
    foreach ($tokens as $token) {
        if (! str_contains($content, $token)) {
            return false;
        }
    }

    return true;
};

$gate(
    'staging_command_real_round_trips',
    $containsAll($files['command'], [
        'select version() as version',
        'getMigrationFiles',
        'Redis::setex',
        "Queue::connection('redis')->size",
        'Storage::disk',
        'r2_private_round_trip',
        'r2_public_delivery',
        'r2_cors',
        'r2_cleanup',
    ]),
    'Staging acceptance must exercise MySQL, migrations, Redis, queue, R2 delivery, CORS and cleanup.',
);

$gate(
    'readiness_requires_lock_and_schema',
    $containsAll($files['readiness'], [
        "'composer_lock'",
        "'database'",
        "'redis'",
        "'schema_current'",
        "'media_activation'",
    ]),
    'Backend readiness must fail closed when the lockfile, database, Redis, schema or enabled media provider is unavailable.',
);

$gate(
    'staging_environment_is_safe',
    $containsAll($files['environment'], [
        'APP_ENV=staging',
        'APP_DEBUG=false',
        'ROSTA_PAYMENT_ENABLED=false',
        'ROSTA_REFUND_ENABLED=false',
        'ROSTA_SMS_ENABLED=false',
        'ROSTA_MEDIA_UPLOADS_ENABLED=true',
        'ROSTA_MEDIA_UPLOAD_DISK=s3',
        'SESSION_SECURE_COOKIE=true',
        'SESSION_ENCRYPT=true',
        'S3_ENDPOINT=https://CHANGE_ME.r2.cloudflarestorage.com',
    ]),
    'Staging must keep money/SMS disabled while requiring secure sessions and real Cloudflare R2.',
);

$gate(
    'private_runtime_network',
    $containsAll($files['compose'], [
        'internal: true',
        'mysql:8.4',
        'redis:7.4-alpine',
        'api-web:',
        'frontend:',
        'edge:',
        'no-new-privileges:true',
    ])
        && ! str_contains($files['compose'], '"3306:3306"')
        && ! str_contains($files['compose'], '"6379:6379"'),
    'MySQL, Redis and PHP must remain private behind the TLS edge.',
);

$gate(
    'runtime_view_cache_is_container_local',
    $containsAll($files['entrypoint'], [
        'VIEW_COMPILED_PATH="${VIEW_COMPILED_PATH:-/tmp/rosta-compiled-views}"',
        'export VIEW_COMPILED_PATH',
        'mkdir -p "$VIEW_COMPILED_PATH"',
        'php artisan view:cache --no-interaction',
    ]) && ! str_contains($files['entrypoint'], 'storage/framework/views'),
    'API, worker and scheduler containers must compile Blade views in private paths instead of racing on shared storage.',
);

$gate(
    'deploy_is_backup_first_and_lock_consuming',
    $containsAll($files['deploy'], [
        'require_committed_composer_lock',
        'backend/composer.lock is required',
        'composer validate --strict',
        'composer audit --locked',
        'composer install',
        'composer check',
        'backup_database',
        'php artisan migrate --force',
        'acceptance.sh',
        'record_release_tag',
        'flock -n',
    ])
        && ! str_contains($files['deploy'], 'composer update')
        && ! str_contains($files['deploy'], 'ensure_composer_lock'),
    'Deployment must serialize, consume the reviewed lock, back up, migrate, accept and record the release.',
);

$gate(
    'deploy_uses_immutable_release_candidate_sha',
    $containsAll($files['deploy_workflow'], [
        'release_sha:',
        '40-character commit SHA',
        'ref: ${{ inputs.release_sha }}',
        'git merge-base --is-ancestor',
        'origin/integration/rosta-release-candidate',
    ])
        && ! str_contains($files['deploy_workflow'], 'release_ref:')
        && ! str_contains($files['deploy_workflow'], 'agent/phase-22'),
    'Deployment must select an exact immutable commit frozen on the release-candidate branch.',
);

$gate(
    'host_acceptance_is_external',
    $containsAll($files['acceptance'], [
        'rosta:readiness --json',
        'rosta:staging-acceptance --json',
        'ssr_home',
        'robots_noindex',
        'security_headers',
        'cors_credentials',
        'secure_csrf_cookie',
        'acceptance.json.sha256',
    ]),
    'Host acceptance must verify external HTTPS, SSR, noindex, CORS, cookies and signed evidence.',
);

$gate(
    'rollback_is_forward_schema_only',
    $containsAll($files['rollback'], [
        'backup_database',
        '--no-build',
        'acceptance.sh',
        'Rollback candidate failed acceptance',
    ]) && ! str_contains($files['rollback'], 'migrate:rollback'),
    'Rollback may switch immutable images but must not run automatic down migrations.',
);

$gate(
    'whole_bean_boundary_preserved',
    ! preg_match('/grind[_-]?(selector|state)|grind_option/i', implode("\n", $files)),
    'Staging code must not introduce a grind selector, option or state.',
);

$failed = array_values(array_filter($gates, static fn (array $gate): bool => ! $gate['passed']));
file_put_contents($root.'/staging-deployment-contract-audit.json', json_encode([
    'generatedAt' => gmdate(DATE_ATOM),
    'marker' => 'phase22_backend_staging=ready',
    'passed' => $failed === [],
    'gates' => $gates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, "Phase 22 backend staging audit failed:\n");
    foreach ($failed as $gate) {
        fwrite(STDERR, "- {$gate['name']}: {$gate['evidence']}\n");
    }

    exit(1);
}

echo 'Phase 22 backend staging audit passed ('.count($gates)." gates).\n";
