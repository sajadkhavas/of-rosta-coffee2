<?php

namespace Tests\Feature;

use App\Enums\MediaUploadStatus;
use App\Exceptions\ApiDomainException;
use App\Models\MediaUploadIntent;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\MediaUploadService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaUploadCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config([
            'rosta.media_uploads.enabled' => true,
            'rosta.media_uploads.disk' => 's3',
            'rosta.media_uploads.public_base_url' => 'https://media.test',
            'rosta.catalog.allowed_media_hosts' => ['media.test'],
        ]);
    }

    public function test_completion_registers_once_after_object_validation(): void
    {
        [$user, $roastery, $intent] = $this->fixture('complete');
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl5ZAAAAABJRU5ErkJggg==',
            true,
        );
        $this->assertIsString($png);
        Storage::disk('s3')->put($intent->object_key, $png);
        $intent->forceFill([
            'size_bytes' => strlen($png),
            'checksum_sha256' => hash('sha256', $png),
        ])->save();
        $request = Request::create('/api/v1/media-test', 'POST');
        $service = app(MediaUploadService::class);

        $asset = $service->complete($roastery, $user, $intent->id, [
            'alt' => 'تصویر بسته قهوه دانه کامل',
            'width' => 1,
            'height' => 1,
            'blur_data_url' => null,
        ], $request);
        $replayed = $service->complete($roastery, $user, $intent->id, [
            'alt' => 'این مقدار در اجرای تکراری استفاده نمی‌شود',
            'width' => 1,
            'height' => 1,
            'blur_data_url' => null,
        ], $request);

        $this->assertSame($asset->id, $replayed->id);
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseHas('media_upload_intents', [
            'id' => $intent->id,
            'media_asset_id' => $asset->id,
            'status' => MediaUploadStatus::Completed->value,
        ]);
        $this->assertSame(
            'https://media.test/roasteries/'.rawurlencode($roastery->id).'/uploads/2026/07/object-complete.png',
            $asset->sources[0]['url'],
        );
    }

    public function test_another_user_or_roastery_cannot_complete_the_intent(): void
    {
        [$owner, $roastery, $intent] = $this->fixture('scope');
        $foreignUser = User::factory()->create();
        $foreignRoastery = Roastery::query()->create([
            'name' => 'روستری خارجی رسانه',
            'slug' => 'foreign-media-roastery',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $request = Request::create('/api/v1/media-test', 'POST');
        $service = app(MediaUploadService::class);
        $payload = [
            'alt' => 'تصویر تست',
            'width' => 1,
            'height' => 1,
            'blur_data_url' => null,
        ];

        try {
            $service->complete($roastery, $foreignUser, $intent->id, $payload, $request);
            $this->fail('Foreign user should not resolve the upload intent.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        try {
            $service->complete($foreignRoastery, $owner, $intent->id, $payload, $request);
            $this->fail('Foreign roastery should not resolve the upload intent.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_size_mismatch_fails_without_creating_public_asset(): void
    {
        [$user, $roastery, $intent] = $this->fixture('mismatch');
        Storage::disk('s3')->put($intent->object_key, 'not-the-expected-size');

        try {
            app(MediaUploadService::class)->complete(
                $roastery,
                $user,
                $intent->id,
                [
                    'alt' => 'تصویر نامعتبر',
                    'width' => 1,
                    'height' => 1,
                    'blur_data_url' => null,
                ],
                Request::create('/api/v1/media-test', 'POST'),
            );
            $this->fail('Size mismatch must fail closed.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('catalog.media_size_mismatch', $exception->errorCode);
        }

        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseHas('media_upload_intents', [
            'id' => $intent->id,
            'status' => MediaUploadStatus::Failed->value,
            'failure_reason' => 'size_mismatch',
        ]);
    }

    /** @return array{User, Roastery, MediaUploadIntent} */
    private function fixture(string $suffix): array
    {
        $user = User::factory()->create();
        $roastery = Roastery::query()->create([
            'name' => 'روستری رسانه '.$suffix,
            'slug' => 'media-roastery-'.$suffix,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $intent = MediaUploadIntent::query()->create([
            'roastery_id' => $roastery->id,
            'user_id' => $user->id,
            'disk' => 's3',
            'object_key' => "roasteries/{$roastery->id}/uploads/2026/07/object-{$suffix}.png",
            'original_filename' => "coffee-{$suffix}.png",
            'mime_type' => 'image/png',
            'size_bytes' => 999,
            'checksum_sha256' => str_repeat('a', 64),
            'status' => MediaUploadStatus::Pending,
            'expires_at' => now()->addMinutes(15),
        ]);

        return [$user, $roastery, $intent];
    }
}
