<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class StagingAcceptance extends Command
{
    protected $signature = 'rosta:staging-acceptance {--json : Print JSON only}';

    protected $description = 'Run destructive-safe staging acceptance against MySQL, Redis, queue and Cloudflare R2';

    public function handle(Migrator $migrator): int
    {
        $checks = [];
        $startedAt = microtime(true);

        $this->record(
            $checks,
            'environment',
            app()->environment('staging'),
            'Application environment is staging',
        );
        $this->record(
            $checks,
            'debug_disabled',
            config('app.debug') === false,
            'APP_DEBUG is disabled',
        );
        $this->record(
            $checks,
            'financial_providers_disabled',
            config('rosta.payment.enabled', false) === false
                && config('rosta.refund.enabled', false) === false,
            'Payment and refund execution remain disabled on staging',
        );
        $this->record(
            $checks,
            'sms_disabled',
            config('rosta.notifications.enabled', false) === false,
            'SMS delivery remains disabled on staging',
        );
        $this->record(
            $checks,
            'r2_enabled',
            config('rosta.media_uploads.enabled', false) === true
                && config('rosta.media_uploads.disk') === 's3',
            'Media uploads are enabled through the s3/R2 disk',
        );

        try {
            $version = DB::selectOne('select version() as version');
            $this->record(
                $checks,
                'mysql_connection',
                is_object($version) && is_string($version->version ?? null),
                'MySQL responds to a real query',
            );
        } catch (Throwable $exception) {
            $this->recordFailure($checks, 'mysql_connection', $exception);
        }

        try {
            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $ran = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff(array_keys($files), $ran));
            $this->record(
                $checks,
                'migrations_current',
                $pending === [],
                $pending === []
                    ? 'All migrations are applied'
                    : 'Pending migrations: '.implode(', ', $pending),
            );
        } catch (Throwable $exception) {
            $this->recordFailure($checks, 'migrations_current', $exception);
        }

        $redisKey = 'rosta:staging-acceptance:'.Str::ulid();
        $redisValue = bin2hex(random_bytes(24));
        try {
            Redis::setex($redisKey, 60, $redisValue);
            $roundTrip = Redis::get($redisKey);
            Redis::del($redisKey);
            $this->record(
                $checks,
                'redis_round_trip',
                hash_equals($redisValue, (string) $roundTrip),
                'Redis SETEX/GET/DEL round trip succeeds',
            );
        } catch (Throwable $exception) {
            $this->recordFailure($checks, 'redis_round_trip', $exception);
            try {
                Redis::del($redisKey);
            } catch (Throwable) {
                // Cleanup is best-effort after a failed connectivity check.
            }
        }

        try {
            $size = Queue::connection('redis')->size('default');
            $this->record(
                $checks,
                'redis_queue',
                $size >= 0,
                'Redis queue connection is readable',
            );
        } catch (Throwable $exception) {
            $this->recordFailure($checks, 'redis_queue', $exception);
        }

        $diskName = (string) config('rosta.media_uploads.disk', 's3');
        $acceptanceId = (string) Str::ulid();
        $objectKey = '_private/acceptance/'.app()->environment().'/'.$acceptanceId.'.txt';
        $publishedKey = 'published/acceptance/'.app()->environment().'/'.$acceptanceId.'.webp';
        $payload = 'rosta-staging-acceptance:'.bin2hex(random_bytes(32));
        $publishedPayload = base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEALmk0mk0iIiIiIgBoSygABc6zbAAA', true);
        $objectCreated = false;
        $publishedCreated = false;

        try {
            $disk = Storage::disk($diskName);
            $objectCreated = $disk->put($objectKey, $payload);
            $stored = $objectCreated ? $disk->get($objectKey) : null;
            $this->record(
                $checks,
                'r2_private_round_trip',
                $objectCreated && is_string($stored) && hash_equals($payload, $stored),
                'R2 PUT/GET round trip succeeds',
            );

            $publicBase = rtrim((string) config('rosta.media_uploads.public_base_url'), '/');
            $privateUrl = $publicBase.'/'.implode('/', array_map('rawurlencode', explode('/', $objectKey)));
            $privateResponse = Http::timeout(15)->get($privateUrl);
            $this->record(
                $checks,
                'r2_private_origin_denied',
                ! $privateResponse->successful(),
                'Raw upload prefixes are not anonymously readable',
            );

            if (! is_string($publishedPayload)) {
                throw new RuntimeException('Embedded acceptance WebP is invalid.');
            }
            $publishedCreated = $disk->put($publishedKey, $publishedPayload, [
                'ContentType' => 'image/webp',
            ]);
            $publicUrl = $publicBase.'/'.implode('/', array_map('rawurlencode', explode('/', $publishedKey)));
            $publicResponse = Http::timeout(15)->retry(3, 750, throw: false)->get($publicUrl);
            $this->record(
                $checks,
                'r2_public_delivery',
                $publishedCreated
                    && $publicResponse->successful()
                    && hash_equals($publishedPayload, $publicResponse->body()),
                'R2 custom-domain delivery returns only the published image fixture',
            );

            $origin = (string) (config('cors.allowed_origins.0') ?? '');
            $corsResponse = Http::timeout(15)
                ->withHeaders([
                    'Origin' => $origin,
                    'Access-Control-Request-Method' => 'GET',
                    'Access-Control-Request-Headers' => 'content-type',
                ])
                ->send('OPTIONS', $publicUrl);
            $allowedOrigin = (string) $corsResponse->header('Access-Control-Allow-Origin');
            $this->record(
                $checks,
                'r2_cors',
                $origin !== ''
                    && $corsResponse->successful()
                    && in_array($allowedOrigin, [$origin, '*'], true),
                'R2 CORS permits the configured staging frontend origin',
            );
        } catch (Throwable $exception) {
            if (! isset($checks['r2_private_round_trip'])) {
                $this->recordFailure($checks, 'r2_private_round_trip', $exception);
            }
            if (! isset($checks['r2_public_delivery'])) {
                $this->recordFailure($checks, 'r2_public_delivery', $exception);
            }
            if (! isset($checks['r2_private_origin_denied'])) {
                $this->recordFailure($checks, 'r2_private_origin_denied', $exception);
            }
            if (! isset($checks['r2_cors'])) {
                $this->recordFailure($checks, 'r2_cors', $exception);
            }
        } finally {
            if ($objectCreated) {
                try {
                    $disk = Storage::disk($diskName);
                    $privateDeleted = $disk->delete($objectKey);
                    $publishedDeleted = ! $publishedCreated || $disk->delete($publishedKey);
                    $this->record(
                        $checks,
                        'r2_cleanup',
                        $privateDeleted
                            && $publishedDeleted
                            && ! $disk->exists($objectKey)
                            && ! $disk->exists($publishedKey),
                        'Private and published acceptance objects are removed from R2',
                    );
                } catch (Throwable $exception) {
                    $this->recordFailure($checks, 'r2_cleanup', $exception);
                }
            } else {
                $this->record(
                    $checks,
                    'r2_cleanup',
                    false,
                    'Acceptance object was not created, so cleanup could not be verified',
                );
            }
        }

        $failed = array_values(array_filter(
            $checks,
            static fn (array $check): bool => ! $check['passed'],
        ));
        $report = [
            'accepted' => $failed === [],
            'environment' => app()->environment(),
            'contract_version' => config('rosta.contract_version'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'generated_at' => gmdate(DATE_ATOM),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            foreach ($checks as $name => $check) {
                $this->line(sprintf(
                    '[%s] %s — %s',
                    $check['passed'] ? 'PASS' : 'FAIL',
                    $name,
                    $check['evidence'],
                ));
            }
        }

        return $report['accepted'] ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, array{passed: bool, evidence: string}> $checks */
    private function record(array &$checks, string $name, bool $passed, string $evidence): void
    {
        $checks[$name] = compact('passed', 'evidence');
    }

    /** @param array<string, array{passed: bool, evidence: string}> $checks */
    private function recordFailure(array &$checks, string $name, Throwable $exception): void
    {
        $this->record(
            $checks,
            $name,
            false,
            sprintf('%s failed (%s)', $name, $exception::class),
        );
    }
}
