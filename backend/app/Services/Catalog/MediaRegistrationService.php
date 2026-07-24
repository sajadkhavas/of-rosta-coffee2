<?php

namespace App\Services\Catalog;

use App\Exceptions\ApiDomainException;
use App\Models\MediaAsset;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MediaRegistrationService
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(
        Roastery $roastery,
        User $actor,
        array $data,
        Request $request,
    ): MediaAsset {
        $allowedHosts = (array) config('rosta.catalog.allowed_media_hosts', []);
        if ($allowedHosts === []) {
            throw new ApiDomainException(
                'catalog.media_storage_unavailable',
                'فضای ذخیره‌سازی رسانه هنوز پیکربندی نشده است.',
                503,
            );
        }

        foreach ($data['sources'] as $source) {
            $url = (string) $source['url'];
            $parts = parse_url($url);
            $host = is_array($parts) && isset($parts['host'])
                ? strtolower((string) $parts['host'])
                : null;

            if (
                ! is_array($parts)
                || ! isset($parts['scheme'], $parts['host'])
                || strtolower((string) $parts['scheme']) !== 'https'
                || isset($parts['user'], $parts['pass'])
                || $host === null
                || ! in_array($host, $allowedHosts, true)
            ) {
                throw new ApiDomainException(
                    'catalog.media_url_invalid',
                    'نشانی رسانه باید HTTPS و متعلق به فضای ذخیره‌سازی تأییدشده رستا باشد.',
                    422,
                );
            }
        }

        return DB::transaction(function () use ($roastery, $actor, $data, $request): MediaAsset {
            $lockedRoastery = Roastery::query()
                ->lockForUpdate()
                ->findOrFail($roastery->id);

            if ($lockedRoastery->mediaAssets()->count() >= (int) config('rosta.catalog.max_media_per_roastery')) {
                throw new ApiDomainException(
                    'catalog.media_limit_reached',
                    'سقف رسانه‌های این روستری تکمیل شده است.',
                    409,
                );
            }

            $asset = MediaAsset::query()->create([
                'roastery_id' => $roastery->id,
                'alt' => $data['alt'],
                'width' => $data['width'],
                'height' => $data['height'],
                'blur_data_url' => $data['blur_data_url'] ?? null,
                'sources' => $data['sources'],
            ]);

            $this->audit->record(
                'catalog.media.registered',
                actor: $actor,
                auditable: $asset,
                metadata: ['roastery_id' => $roastery->id],
                request: $request,
            );

            return $asset;
        });
    }
}
