<?php

namespace App\Services\Catalog;

use App\Enums\MediaUploadStatus;
use App\Exceptions\ApiDomainException;
use App\Exceptions\MediaProcessingException;
use App\Jobs\ProcessMediaUpload;
use App\Models\MediaUploadIntent;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Media\SecureImageProcessor;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class MediaUploadService
{
    public function __construct(
        private readonly MediaRegistrationService $registration,
        private readonly AuditRecorder $audit,
        private readonly SecureImageProcessor $processor,
    ) {}

    /**
     * @param  array{filename: string, mime_type: string, size_bytes: int, checksum_sha256: string}  $data
     * @return array{intent: MediaUploadIntent, upload_url: string, headers: array<string, string>}
     */
    public function createIntent(
        Roastery $roastery,
        User $actor,
        array $data,
        Request $request,
    ): array {
        $this->assertConfigured();
        $mime = strtolower(trim($data['mime_type']));
        $extension = $this->extensionFor($mime);
        $intentId = (string) Str::ulid();
        $objectKey = sprintf(
            '_private/roasteries/%s/uploads/%s/%s/%s.%s',
            $roastery->id,
            now()->format('Y'),
            now()->format('m'),
            $intentId,
            $extension,
        );
        $expiresAt = now()->addMinutes(
            max(1, min(60, (int) config('rosta.media_uploads.ttl_minutes', 15))),
        );
        $checksumBinary = hex2bin(strtolower($data['checksum_sha256']));
        if ($checksumBinary === false) {
            throw new ApiDomainException(
                'catalog.media_checksum_invalid',
                'Checksum فایل معتبر نیست.',
                422,
            );
        }

        $intent = new MediaUploadIntent;
        $intent->id = $intentId;
        $intent->fill([
            'roastery_id' => $roastery->id,
            'user_id' => $actor->id,
            'disk' => $this->diskName(),
            'object_key' => $objectKey,
            'original_filename' => trim($data['filename']),
            'mime_type' => $mime,
            'size_bytes' => $data['size_bytes'],
            'checksum_sha256' => strtolower($data['checksum_sha256']),
            'status' => MediaUploadStatus::Uploading,
            'expires_at' => $expiresAt,
        ]);
        $intent->save();

        try {
            $signed = $this->disk()->temporaryUploadUrl(
                $objectKey,
                $expiresAt,
                [
                    'ContentType' => $mime,
                    'ChecksumSHA256' => base64_encode($checksumBinary),
                ],
            );
        } catch (Throwable) {
            $this->failIntent($intent, 'signed_url_unavailable');

            throw new ApiDomainException(
                'catalog.media_storage_unavailable',
                'ساخت آدرس امن بارگذاری ممکن نیست.',
                503,
            );
        }

        if (! isset($signed['url']) || ! is_string($signed['url'])) {
            $this->failIntent($intent, 'invalid_signed_url_response');

            throw new ApiDomainException(
                'catalog.media_storage_unavailable',
                'فضای ذخیره‌سازی پاسخ معتبر نداد.',
                503,
            );
        }

        $headers = [];
        foreach (($signed['headers'] ?? []) as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $headers[$name] = (string) $value;
            }
        }

        $this->audit->record(
            'catalog.media_upload_intent.created',
            actor: $actor,
            auditable: $intent,
            metadata: [
                'roastery_id' => $roastery->id,
                'declared_mime_type' => $mime,
                'declared_size_bytes' => $intent->size_bytes,
            ],
            request: $request,
        );

        return [
            'intent' => $intent,
            'upload_url' => $signed['url'],
            'headers' => $headers,
        ];
    }

    /** @param  array{alt: string}  $data */
    public function complete(
        Roastery $roastery,
        User $actor,
        string $uploadId,
        array $data,
        Request $request,
    ): MediaUploadIntent {
        $this->assertConfigured();

        /** @var array{MediaUploadIntent, bool} $result */
        $result = DB::transaction(function () use (
            $roastery,
            $actor,
            $uploadId,
            $data,
            $request,
        ): array {
            $intent = $this->ownedIntent($roastery, $actor, $uploadId, lock: true);

            if (in_array($intent->status, [MediaUploadStatus::Ready, MediaUploadStatus::Processing], true)) {
                return [$intent, false];
            }
            if (
                ! in_array($intent->status, [MediaUploadStatus::Uploading, MediaUploadStatus::Pending], true)
                || $intent->expires_at->isPast()
            ) {
                throw new ApiDomainException(
                    'catalog.media_upload_not_processable',
                    'این بارگذاری دیگر قابل پردازش نیست.',
                    409,
                );
            }

            if (! $this->disk($intent->disk)->exists($intent->object_key)) {
                throw new ApiDomainException(
                    'catalog.media_upload_missing',
                    'فایل بارگذاری‌شده در فضای ذخیره‌سازی پیدا نشد.',
                    409,
                );
            }

            $intent->forceFill([
                'alt_text' => trim($data['alt']),
                'status' => MediaUploadStatus::Processing,
                'processing_attempts' => $intent->processing_attempts + 1,
                'processing_started_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
                'failure_retryable' => false,
            ])->save();

            $this->audit->record(
                'catalog.media_upload.processing',
                actor: $actor,
                auditable: $intent,
                metadata: [
                    'roastery_id' => $roastery->id,
                    'processing_attempt' => $intent->processing_attempts,
                ],
                request: $request,
            );

            return [$intent, true];
        }, 3);
        [$intent, $dispatch] = $result;

        if ($dispatch) {
            ProcessMediaUpload::dispatch($intent->id);
        }

        return $intent->fresh(['mediaAsset']) ?? $intent;
    }

    public function status(
        Roastery $roastery,
        User $actor,
        string $uploadId,
    ): MediaUploadIntent {
        return $this->ownedIntent($roastery, $actor, $uploadId)->loadMissing('mediaAsset');
    }

    public function retry(
        Roastery $roastery,
        User $actor,
        string $uploadId,
        Request $request,
    ): MediaUploadIntent {
        $this->assertConfigured();

        $intent = DB::transaction(function () use ($roastery, $actor, $uploadId, $request): MediaUploadIntent {
            $intent = $this->ownedIntent($roastery, $actor, $uploadId, lock: true);
            $maxAttempts = max(1, (int) config('rosta.media_uploads.max_processing_attempts', 3));
            if (
                $intent->status !== MediaUploadStatus::Failed
                || ! $intent->failure_retryable
                || $intent->processing_attempts >= $maxAttempts
            ) {
                throw new ApiDomainException(
                    'catalog.media_retry_forbidden',
                    'این خطای پردازش قابل تلاش دوباره نیست.',
                    409,
                );
            }

            $intent->forceFill([
                'status' => MediaUploadStatus::Processing,
                'processing_attempts' => $intent->processing_attempts + 1,
                'processing_started_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
                'failure_retryable' => false,
            ])->save();

            $this->audit->record(
                'catalog.media_upload.retry_requested',
                actor: $actor,
                auditable: $intent,
                metadata: ['processing_attempt' => $intent->processing_attempts],
                request: $request,
            );

            return $intent;
        }, 3);

        ProcessMediaUpload::dispatch($intent->id);

        return $intent->fresh(['mediaAsset']) ?? $intent;
    }

    public function process(string $uploadId): void
    {
        $lock = Cache::lock('media:process:'.hash('sha256', $uploadId), 120);
        if (! $lock->get()) {
            return;
        }

        $writtenKeys = [];
        try {
            $intent = MediaUploadIntent::query()->with(['roastery', 'user'])->find($uploadId);
            if (! $intent || $intent->status !== MediaUploadStatus::Processing || $intent->media_asset_id) {
                return;
            }

            $disk = $this->disk($intent->disk);
            [$bytes, $actualSize, $actualChecksum] = $this->readObject($disk, $intent->object_key);
            $intent->forceFill([
                'actual_size_bytes' => $actualSize,
                'actual_checksum_sha256' => $actualChecksum,
            ])->save();
            if ($actualSize !== $intent->size_bytes) {
                throw new MediaProcessingException('size_mismatch', rejected: true);
            }
            if (! hash_equals(strtolower((string) $intent->checksum_sha256), $actualChecksum)) {
                throw new MediaProcessingException('checksum_mismatch', rejected: true);
            }

            $processed = $this->processor->process($bytes, strtolower((string) $intent->mime_type));
            unset($bytes);
            $version = $this->variantVersion();
            $sources = [];

            foreach ($processed['variants'] as $variant) {
                $key = sprintf(
                    'published/roasteries/%s/media/%s/%s/%d.%s',
                    $intent->roastery_id,
                    $version,
                    $intent->id,
                    $variant['width'],
                    $variant['extension'],
                );
                $stored = $disk->put($key, $variant['bytes'], [
                    'visibility' => 'private',
                    'ContentType' => $variant['mime'],
                    'CacheControl' => 'public, max-age=31536000, immutable',
                ]);
                if (! $stored) {
                    throw new RuntimeException('Variant storage write failed.');
                }
                $writtenKeys[] = $key;
                $sources[] = [
                    'url' => $this->publicUrl($key),
                    'width' => $variant['width'],
                    'height' => $variant['height'],
                    'format' => $variant['format'],
                    'size_bytes' => strlen($variant['bytes']),
                    'checksum_sha256' => $variant['checksum_sha256'],
                ];
            }

            $this->finalize($intent, $processed, $sources, $version);
            $writtenKeys = [];
            try {
                $disk->delete($intent->object_key);
            } catch (Throwable) {
                // Ready source cleanup is retried by the scheduled cleanup pass.
            }
        } catch (MediaProcessingException $exception) {
            $this->recordProcessingFailure($uploadId, $exception->reasonCode, $exception->rejected);
            if ($exception->rejected) {
                $this->deleteSource($uploadId);
            }
            $this->deleteKeys($uploadId, $writtenKeys);
        } catch (Throwable) {
            $this->recordProcessingFailure($uploadId, 'processing_dependency_unavailable', rejected: false);
            $this->deleteKeys($uploadId, $writtenKeys);
        } finally {
            $lock->release();
        }
    }

    public function recordQueueFailure(string $uploadId): void
    {
        $this->recordProcessingFailure($uploadId, 'worker_terminated', rejected: false);
    }

    public function expireDue(int $limit = 500): int
    {
        $intents = MediaUploadIntent::query()
            ->whereIn('status', [MediaUploadStatus::Uploading->value, MediaUploadStatus::Pending->value])
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min(2000, $limit)))
            ->get();
        $expired = 0;

        foreach ($intents as $intent) {
            DB::transaction(function () use ($intent, &$expired): void {
                $locked = MediaUploadIntent::query()->whereKey($intent->id)->lockForUpdate()->first();
                if (
                    ! $locked
                    || ! in_array($locked->status, [MediaUploadStatus::Uploading, MediaUploadStatus::Pending], true)
                    || $locked->expires_at->isFuture()
                ) {
                    return;
                }

                $this->disk($locked->disk)->delete($locked->object_key);
                $locked->forceFill([
                    'status' => MediaUploadStatus::Expired,
                    'failure_reason' => 'upload_intent_expired',
                ])->save();
                $expired++;
            }, 3);
        }

        return $expired;
    }

    public function cleanupTerminal(int $limit = 500): int
    {
        $cutoff = now()->subHours(max(1, (int) config('rosta.media_uploads.orphan_retention_hours', 24)));
        $intents = MediaUploadIntent::query()
            ->whereIn('status', [
                MediaUploadStatus::Ready->value,
                MediaUploadStatus::Rejected->value,
                MediaUploadStatus::Failed->value,
            ])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(max(1, min(2000, $limit)))
            ->get();

        foreach ($intents as $intent) {
            $this->disk($intent->disk)->delete($intent->object_key);
        }

        return $intents->count();
    }

    private function ownedIntent(
        Roastery $roastery,
        User $actor,
        string $uploadId,
        bool $lock = false,
    ): MediaUploadIntent {
        $query = MediaUploadIntent::query()
            ->where('roastery_id', $roastery->id)
            ->where('user_id', $actor->id)
            ->whereKey($uploadId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    /** @return array{string, int, string} */
    private function readObject(FilesystemAdapter $disk, string $objectKey): array
    {
        $stream = $disk->readStream($objectKey);
        if (! is_resource($stream)) {
            throw new RuntimeException('Storage stream is unavailable.');
        }

        $maxBytes = max(1, (int) config('rosta.media_uploads.max_size_bytes', 12_000_000));
        $bytes = '';
        $hash = hash_init('sha256');
        $size = 0;
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 64 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Storage stream read failed.');
                }
                $size += strlen($chunk);
                if ($size > $maxBytes) {
                    throw new MediaProcessingException('byte_limit_exceeded', rejected: true);
                }
                hash_update($hash, $chunk);
                $bytes .= $chunk;
            }
        } finally {
            fclose($stream);
        }

        return [$bytes, $size, hash_final($hash)];
    }

    /**
     * @param  array{detected_mime: string, width: int, height: int, blur_data_url: string, variants: array<mixed>}  $processed
     * @param  list<array<string, int|string>>  $sources
     */
    private function finalize(
        MediaUploadIntent $intent,
        array $processed,
        array $sources,
        string $version,
    ): void {
        DB::transaction(function () use ($intent, $processed, $sources, $version): void {
            $locked = MediaUploadIntent::query()->whereKey($intent->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === MediaUploadStatus::Ready) {
                return;
            }
            if ($locked->status !== MediaUploadStatus::Processing || ! $locked->roastery || ! $locked->user) {
                throw new RuntimeException('Media intent state changed during processing.');
            }

            $asset = $this->registration->register(
                $locked->roastery,
                $locked->user,
                [
                    'alt' => (string) $locked->alt_text,
                    'width' => $processed['width'],
                    'height' => $processed['height'],
                    'blur_data_url' => $processed['blur_data_url'],
                    'variant_version' => $version,
                    'sources' => $sources,
                ],
                Request::create('/internal/media-processing', 'POST'),
            );

            $locked->forceFill([
                'media_asset_id' => $asset->id,
                'status' => MediaUploadStatus::Ready,
                'detected_mime_type' => $processed['detected_mime'],
                'actual_size_bytes' => $locked->actual_size_bytes,
                'actual_checksum_sha256' => $locked->actual_checksum_sha256,
                'detected_width' => $processed['width'],
                'detected_height' => $processed['height'],
                'variant_version' => $version,
                'completed_at' => now(),
                'ready_at' => now(),
                'failed_at' => null,
                'rejected_at' => null,
                'failure_reason' => null,
                'failure_retryable' => false,
            ])->save();

            $this->audit->record(
                'catalog.media_upload.ready',
                actor: $locked->user,
                auditable: $locked,
                metadata: [
                    'roastery_id' => $locked->roastery_id,
                    'media_asset_id' => $asset->id,
                    'detected_mime_type' => $processed['detected_mime'],
                    'detected_width' => $processed['width'],
                    'detected_height' => $processed['height'],
                    'variant_count' => count($sources),
                    'variant_version' => $version,
                ],
                request: Request::create('/internal/media-processing', 'POST'),
            );
        }, 3);
    }

    private function recordProcessingFailure(string $uploadId, string $reason, bool $rejected): void
    {
        DB::transaction(function () use ($uploadId, $reason, $rejected): void {
            $intent = MediaUploadIntent::query()->whereKey($uploadId)->lockForUpdate()->first();
            if (! $intent || $intent->status !== MediaUploadStatus::Processing) {
                return;
            }
            $maxAttempts = max(1, (int) config('rosta.media_uploads.max_processing_attempts', 3));
            $retryable = ! $rejected && $intent->processing_attempts < $maxAttempts;
            $intent->forceFill([
                'status' => $rejected ? MediaUploadStatus::Rejected : MediaUploadStatus::Failed,
                'failure_reason' => $reason,
                'failure_retryable' => $retryable,
                'failed_at' => now(),
                'rejected_at' => $rejected ? now() : null,
            ])->save();

            $this->audit->record(
                $rejected ? 'catalog.media_upload.rejected' : 'catalog.media_upload.failed',
                actor: $intent->user,
                auditable: $intent,
                metadata: [
                    'reason_code' => $reason,
                    'retryable' => $retryable,
                    'processing_attempt' => $intent->processing_attempts,
                ],
                request: Request::create('/internal/media-processing', 'POST'),
            );
        }, 3);
    }

    private function deleteSource(string $uploadId): void
    {
        try {
            $intent = MediaUploadIntent::query()->find($uploadId);
            if ($intent) {
                $this->disk($intent->disk)->delete($intent->object_key);
            }
        } catch (Throwable) {
            // Scheduled terminal cleanup provides a second bounded attempt.
        }
    }

    /** @param list<string> $keys */
    private function deleteKeys(string $uploadId, array $keys): void
    {
        if ($keys === []) {
            return;
        }
        try {
            $intent = MediaUploadIntent::query()->find($uploadId);
            if ($intent) {
                $this->disk($intent->disk)->delete($keys);
            }
        } catch (Throwable) {
            // Versioned keys are overwritten on retry and pruned by object lifecycle.
        }
    }

    private function failIntent(MediaUploadIntent $intent, string $reason): void
    {
        $intent->forceFill([
            'status' => MediaUploadStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => $reason,
            'failure_retryable' => true,
        ])->save();
    }

    private function assertConfigured(): void
    {
        if (
            ! config('rosta.media_uploads.enabled', false)
            || trim((string) config('rosta.media_uploads.public_base_url')) === ''
            || preg_match(
                '/^[A-Za-z0-9_-]{1,32}$/',
                trim((string) config('rosta.media_uploads.variant_version', 'v1')),
            ) !== 1
            || ! $this->processor->isAvailable()
        ) {
            throw new ApiDomainException(
                'catalog.media_storage_unavailable',
                'خط پردازش امن رسانه هنوز آماده نیست.',
                503,
            );
        }
    }

    private function diskName(): string
    {
        return trim((string) config('rosta.media_uploads.disk', 's3'));
    }

    private function disk(?string $name = null): FilesystemAdapter
    {
        return Storage::disk($name ?: $this->diskName());
    }

    private function publicUrl(string $objectKey): string
    {
        $base = rtrim((string) config('rosta.media_uploads.public_base_url'), '/');
        $parts = parse_url($base);
        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'], $parts['pass'])
        ) {
            throw new ApiDomainException(
                'catalog.media_public_url_invalid',
                'نشانی عمومی CDN معتبر نیست.',
                503,
            );
        }
        $encoded = implode('/', array_map('rawurlencode', explode('/', $objectKey)));

        return $base.'/'.$encoded;
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => throw new ApiDomainException(
                'catalog.media_mime_invalid',
                'نوع فایل پشتیبانی نمی‌شود.',
                422,
            ),
        };
    }

    private function variantVersion(): string
    {
        $version = trim((string) config('rosta.media_uploads.variant_version', 'v1'));
        if (preg_match('/^[A-Za-z0-9_-]{1,32}$/', $version) !== 1) {
            throw new RuntimeException('Media variant version is invalid.');
        }

        return $version;
    }
}
