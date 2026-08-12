<?php

namespace Tests\Feature;

use App\Enums\MediaUploadStatus;
use App\Enums\Role;
use App\Models\MediaUploadIntent;
use App\Models\Roastery;
use App\Models\User;
use App\Services\Catalog\MediaUploadService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class MediaUploadCompletionTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config([
            'rosta.media_uploads.enabled' => true,
            'rosta.media_uploads.disk' => 's3',
            'rosta.media_uploads.public_base_url' => 'https://media.test',
            'rosta.media_uploads.variant_widths' => [32, 64],
            'rosta.media_uploads.variant_version' => 'v1',
            'rosta.catalog.allowed_media_hosts' => ['media.test'],
        ]);
    }

    public function test_server_truth_generates_versioned_variants_once(): void
    {
        $png = $this->imageBlob('png', 64, 48);
        [$user, $roastery, $intent] = $this->fixture('complete', $png, 'image/png');
        $request = Request::create('/api/v1/media-test', 'POST');
        $service = app(MediaUploadService::class);

        $completed = $service->complete($roastery, $user, $intent->id, [
            'alt' => 'تصویر بسته قهوه دانه کامل',
        ], $request);
        $this->assertSame(
            MediaUploadStatus::Ready,
            $completed->status,
            $this->failureContext($completed),
        );
        $replayed = $service->complete($roastery, $user, $intent->id, [
            'alt' => 'این مقدار در اجرای تکراری استفاده نمی‌شود',
        ], $request);

        $this->assertSame($completed->media_asset_id, $replayed->media_asset_id);
        $this->assertDatabaseCount('media_assets', 1);
        $asset = $completed->mediaAsset;
        $this->assertNotNull($asset);
        $this->assertSame(64, $asset->width);
        $this->assertSame(48, $asset->height);
        $this->assertSame('v1', $asset->variant_version);
        $this->assertSame('jpeg', $asset->sources[0]['format']);
        $this->assertStringContainsString('/published/roasteries/', $asset->sources[0]['url']);
        $this->assertSame(
            ['avif', 'jpeg', 'webp'],
            collect($asset->sources)->pluck('format')->unique()->sort()->values()->all(),
        );
        Storage::disk('s3')->assertMissing($intent->object_key);
    }

    public function test_client_dimensions_and_blur_are_not_accepted_as_completion_truth(): void
    {
        [$user, $roastery, $intent] = $this->fixture(
            'client-truth',
            $this->imageBlob('png', 3, 2),
            'image/png',
        );

        $this->authenticateWithRole($user, Role::RoasteryOwner, 'roastery', $roastery->id);

        $this->postJson(
            "/api/v1/seller/roasteries/{$roastery->id}/media/uploads/{$intent->id}/complete",
            [
                'alt' => 'تصویر معتبر',
                'width' => 9999,
                'height' => 9999,
                'blur_data_url' => 'data:image/png;base64,client-controlled',
            ],
        )->assertUnprocessable();

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_checksum_mismatch_is_rejected_and_private_source_is_deleted(): void
    {
        $png = $this->imageBlob('png', 2, 2);
        [$user, $roastery, $intent] = $this->fixture('checksum', $png, 'image/png');
        $intent->forceFill(['checksum_sha256' => str_repeat('a', 64)])->save();

        $result = app(MediaUploadService::class)->complete(
            $roastery,
            $user,
            $intent->id,
            ['alt' => 'تصویر نامعتبر'],
            Request::create('/api/v1/media-test', 'POST'),
        );

        $this->assertSame(MediaUploadStatus::Rejected, $result->status);
        $this->assertSame('checksum_mismatch', $result->failure_reason);
        $this->assertFalse($result->failure_retryable);
        $this->assertDatabaseCount('media_assets', 0);
        Storage::disk('s3')->assertMissing($intent->object_key);
    }

    public function test_spoofed_mime_is_rejected_after_magic_byte_detection(): void
    {
        $jpeg = $this->imageBlob('jpeg', 2, 2);
        [$user, $roastery, $intent] = $this->fixture('spoof', $jpeg, 'image/png');

        $result = app(MediaUploadService::class)->complete(
            $roastery,
            $user,
            $intent->id,
            ['alt' => 'فایل جعل‌شده'],
            Request::create('/api/v1/media-test', 'POST'),
        );

        $this->assertSame(MediaUploadStatus::Rejected, $result->status);
        $this->assertSame('mime_magic_mismatch', $result->failure_reason);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_truncated_image_is_rejected_without_publishing_bytes(): void
    {
        $truncated = "\x89PNG\r\n\x1A\n".str_repeat("\0", 20);
        [$user, $roastery, $intent] = $this->fixture('truncated', $truncated, 'image/png');

        $result = app(MediaUploadService::class)->complete(
            $roastery,
            $user,
            $intent->id,
            ['alt' => 'فایل ناقص'],
            Request::create('/api/v1/media-test', 'POST'),
        );

        $this->assertSame(MediaUploadStatus::Rejected, $result->status);
        $this->assertSame('image_decode_failed', $result->failure_reason);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('s3')->allFiles('published'));
    }

    public function test_oversized_pixel_count_is_rejected_before_variant_generation(): void
    {
        config(['rosta.media_uploads.max_pixels' => 3]);
        $png = $this->imageBlob('png', 2, 2);
        [$user, $roastery, $intent] = $this->fixture('pixels', $png, 'image/png');

        $result = app(MediaUploadService::class)->complete(
            $roastery,
            $user,
            $intent->id,
            ['alt' => 'ابعاد بیش از حد'],
            Request::create('/api/v1/media-test', 'POST'),
        );

        $this->assertSame(MediaUploadStatus::Rejected, $result->status);
        $this->assertSame('image_dimensions_exceeded', $result->failure_reason);
        Storage::disk('s3')->assertMissing($intent->object_key);
    }

    public function test_another_user_or_roastery_cannot_complete_or_read_the_intent(): void
    {
        [$owner, $roastery, $intent] = $this->fixture(
            'scope',
            $this->imageBlob('png', 1, 1),
            'image/png',
        );
        $foreignUser = User::factory()->create();
        $foreignRoastery = Roastery::query()->create([
            'name' => 'روستری خارجی رسانه',
            'slug' => 'foreign-media-roastery',
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $service = app(MediaUploadService::class);

        foreach ([[$roastery, $foreignUser], [$foreignRoastery, $owner]] as [$scope, $actor]) {
            try {
                $service->status($scope, $actor, $intent->id);
                $this->fail('Foreign scope must not resolve the upload intent.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_dependency_failure_is_dead_lettered_and_can_be_retried_explicitly(): void
    {
        $png = $this->imageBlob('png', 64, 64);
        [$user, $roastery, $intent] = $this->fixture('retry', $png, 'image/png');
        $intent->forceFill([
            'disk' => 'unconfigured-storage',
            'alt_text' => 'تصویر قابل تلاش دوباره',
            'status' => MediaUploadStatus::Processing,
            'processing_attempts' => 1,
        ])->save();

        $service = app(MediaUploadService::class);
        $service->process($intent->id);

        $failed = $intent->fresh();
        $this->assertNotNull($failed);
        $this->assertSame(MediaUploadStatus::Failed, $failed->status);
        $this->assertTrue($failed->failure_retryable);
        $this->assertSame('processing_dependency_unavailable', $failed->failure_reason);

        $failed->forceFill(['disk' => 's3'])->save();
        $retried = $service->retry(
            $roastery,
            $user,
            $intent->id,
            Request::create('/api/v1/media-test/retry', 'POST'),
        );

        $this->assertSame(
            MediaUploadStatus::Ready,
            $retried->status,
            $this->failureContext($retried),
        );
        $this->assertDatabaseCount('media_assets', 1);
    }

    /** @return array{User, Roastery, MediaUploadIntent} */
    private function fixture(string $suffix, string $bytes, string $declaredMime): array
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
            'object_key' => "_private/roasteries/{$roastery->id}/uploads/object-{$suffix}.bin",
            'original_filename' => "coffee-{$suffix}.bin",
            'mime_type' => $declaredMime,
            'size_bytes' => strlen($bytes),
            'checksum_sha256' => hash('sha256', $bytes),
            'status' => MediaUploadStatus::Uploading,
            'expires_at' => now()->addMinutes(15),
        ]);
        Storage::disk('s3')->put($intent->object_key, $bytes);

        return [$user, $roastery, $intent];
    }

    private function imageBlob(string $format, int $width, int $height): string
    {
        $image = new Imagick;
        try {
            $image->newImage($width, $height, '#7a4b2a');
            $image->setImageFormat($format);

            return $image->getImageBlob();
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function failureContext(MediaUploadIntent $intent): string
    {
        return sprintf(
            'status=%s reason=%s published=%s',
            $intent->status->value,
            (string) $intent->failure_reason,
            implode(',', Storage::disk('s3')->allFiles('published')),
        );
    }
}
