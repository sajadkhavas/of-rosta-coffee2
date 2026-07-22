<?php

namespace App\Services\Catalog;

use App\Enums\MediaUploadStatus;
use App\Exceptions\ApiDomainException;
use App\Models\MediaAsset;
use App\Models\MediaUploadIntent;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class MediaUploadService
{
    public function __construct(
        private readonly MediaRegistrationService $registration,
        private readonly AuditRecorder $audit,
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
            'roasteries/%s/uploads/%s/%s/%s.%s',
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
            'status' => MediaUploadStatus::Pending,
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
        } catch (Throwable $exception) {
            $intent->forceFill([
                'status' => MediaUploadStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => 'signed_url_unavailable',
            ])->save();

            throw new ApiDomainException(
                'catalog.media_storage_unavailable',
                'ساخت آدرس امن بارگذاری ممکن نیست.',
                503,
                previous: $exception,
            );
        }

        if (
            ! is_array($signed)
            || ! isset($signed['url'])
            || ! is_string($signed['url'])
        ) {
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
                'mime_type' => $mime,
                'size_bytes' => $intent->size_bytes,
            ],
            request: $request,
        );

        return [
            'intent' => $intent,
            'upload_url' => $signed['url'],
            'headers' => $headers,
        ];
    }

    /**
     * @param  array{alt: string, width: int, height: int, blur_data_url?: string|null}  $data
     */
    public function complete(
        Roastery $roastery,
        User $actor,
        string $uploadId,
        array $data,
        Request $request,
    ): MediaAsset {
        $this->assertConfigured();

        return DB::transaction(function () use (
            $roastery,
            $actor,
            $uploadId,
            $data,
            $request,
        ): MediaAsset {
            $intent = MediaUploadIntent::query()
                ->where('roastery_id', $roastery->id)
                ->where('user_id', $actor->id)
                ->whereKey($uploadId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $intent->status === MediaUploadStatus::Completed
                && $intent->mediaAsset
            ) {
                return $intent->mediaAsset;
            }
            if (
                $intent->status !== MediaUploadStatus::Pending
                || $intent->expires_at->isPast()
            ) {
                if ($intent->status === MediaUploadStatus::Pending) {
                    $intent->forceFill([
                        'status' => MediaUploadStatus::Expired,
                        'failure_reason' => 'upload_intent_expired',
                    ])->save();
                }

                throw new ApiDomainException(
                    'catalog.media_upload_expired',
                    'مهلت تکمیل بارگذاری پایان یافته است.',
                    410,
                );
            }

            $disk = $this->disk($intent->disk);
            if (! $disk->exists($intent->object_key)) {
                throw new ApiDomainException(
                    'catalog.media_upload_missing',
                    'فایل بارگذاری‌شده در فضای ذخیره‌سازی پیدا نشد.',
                    409,
                );
            }

            $size = $disk->size($intent->object_key);
            $mime = $disk->mimeType($intent->object_key);
            if ($size !== $intent->size_bytes) {
                $this->failIntent($intent, 'size_mismatch');
                throw new ApiDomainException(
                    'catalog.media_size_mismatch',
                    'حجم فایل با Upload Intent سازگار نیست.',
                    409,
                );
            }
            if (! is_string($mime) || strtolower($mime) !== $intent->mime_type) {
                $this->failIntent($intent, 'mime_mismatch');
                throw new ApiDomainException(
                    'catalog.media_mime_mismatch',
                    'نوع فایل بارگذاری‌شده معتبر نیست.',
                    409,
                );
            }

            $asset = $this->registration->register(
                $roastery,
                $actor,
                [
                    'alt' => trim($data['alt']),
                    'width' => $data['width'],
                    'height' => $data['height'],
                    'blur_data_url' => $data['blur_data_url'] ?? null,
                    'sources' => [[
                        'url' => $this->publicUrl($intent->object_key),
                        'width' => $data['width'],
                        'format' => $this->formatFor($intent->mime_type),
                    ]],
                ],
                $request,
            );

            $intent->forceFill([
                'media_asset_id' => $asset->id,
                'status' => MediaUploadStatus::Completed,
                'completed_at' => now(),
                'failure_reason' => null,
            ])->save();

            $this->audit->record(
                'catalog.media_upload.completed',
                actor: $actor,
                auditable: $intent,
                metadata: [
                    'roastery_id' => $roastery->id,
                    'media_asset_id' => $asset->id,
                    'object_key' => $intent->object_key,
                ],
                request: $request,
            );

            return $asset;
        }, 3);
    }

    public function expireDue(int $limit = 500): int
    {
        $intents = MediaUploadIntent::query()
            ->where('status', MediaUploadStatus::Pending->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min(2000, $limit)))
            ->get();
        $expired = 0;

        foreach ($intents as $intent) {
            DB::transaction(function () use ($intent, &$expired): void {
                $locked = MediaUploadIntent::query()
                    ->whereKey($intent->id)
                    ->lockForUpdate()
                    ->first();
                if (
                    ! $locked
                    || $locked->status !== MediaUploadStatus::Pending
                    || $locked->expires_at->isFuture()
                ) {
                    return;
                }

                $disk = $this->disk($locked->disk);
                if ($disk->exists($locked->object_key)) {
                    $disk->delete($locked->object_key);
                }
                $locked->forceFill([
                    'status' => MediaUploadStatus::Expired,
                    'failure_reason' => 'upload_intent_expired',
                ])->save();
                $expired++;
            }, 3);
        }

        return $expired;
    }

    private function assertConfigured(): void
    {
        if (
            ! config('rosta.media_uploads.enabled', false)
            || trim((string) config('rosta.media_uploads.public_base_url')) === ''
        ) {
            throw new ApiDomainException(
                'catalog.media_storage_unavailable',
                'فضای ذخیره‌سازی رسانه هنوز پیکربندی نشده است.',
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

    private function failIntent(MediaUploadIntent $intent, string $reason): void
    {
        $intent->forceFill([
            'status' => MediaUploadStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ])->save();
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

    private function formatFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => 'jpeg',
        };
    }
}
