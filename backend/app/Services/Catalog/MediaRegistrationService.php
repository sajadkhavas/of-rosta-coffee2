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
    public function __construct(private readonly AuditRecorder $audit) {}

    public function register(Roastery $roastery, User $actor, array $data, Request $request): MediaAsset
    {
        foreach ($data['sources'] as $source) {
            $parts = parse_url((string) $source['url']);
            if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || strtolower((string) $parts['scheme']) !== 'https' || isset($parts['user'], $parts['pass'])) {
                throw new ApiDomainException('catalog.media_url_invalid', 'نشانی رسانه باید HTTPS و بدون اطلاعات احراز هویت باشد.', 422);
            }
        }
        return DB::transaction(function () use ($roastery,$actor,$data,$request): MediaAsset {
            $asset = MediaAsset::query()->create(['roastery_id' => $roastery->id,'alt' => $data['alt'],'width' => $data['width'],'height' => $data['height'],'blur_data_url' => $data['blur_data_url'] ?? null,'sources' => $data['sources']]);
            $this->audit->record('catalog.media.registered', actor: $actor, auditable: $asset, metadata: ['roastery_id' => $roastery->id], request: $request);
            return $asset;
        });
    }
}
