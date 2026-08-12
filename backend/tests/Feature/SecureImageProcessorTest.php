<?php

namespace Tests\Feature;

use App\Exceptions\MediaProcessingException;
use App\Services\Media\SecureImageProcessor;
use Imagick;
use Tests\TestCase;

final class SecureImageProcessorTest extends TestCase
{
    public function test_exif_orientation_is_applied_and_metadata_is_stripped(): void
    {
        $source = new Imagick;
        try {
            $source->newImage(3, 2, '#6f4e37');
            $source->setImageFormat('jpeg');
            $source->setImageOrientation(Imagick::ORIENTATION_RIGHTTOP);
            $source->setImageProperty('comment', 'GPS:35.6892,51.3890');
            $bytes = $source->getImageBlob();
        } finally {
            $source->clear();
            $source->destroy();
        }

        $result = app(SecureImageProcessor::class)->process($bytes, 'image/jpeg');

        $this->assertSame(2, $result['width']);
        $this->assertSame(3, $result['height']);
        $fallback = collect($result['variants'])->firstWhere('format', 'jpeg');
        $this->assertIsArray($fallback);

        $decoded = new Imagick;
        try {
            $decoded->readImageBlob($fallback['bytes']);
            $properties = array_change_key_case($decoded->getImageProperties('*'), CASE_LOWER);
            $this->assertArrayNotHasKey('comment', $properties);
            $this->assertSame([], array_filter(
                array_keys($properties),
                static fn (string $key): bool => str_contains($key, 'gps') || str_contains($key, 'exif'),
            ));
            $this->assertNotSame(Imagick::ORIENTATION_RIGHTTOP, $decoded->getImageOrientation());
        } finally {
            $decoded->clear();
            $decoded->destroy();
        }
    }

    public function test_animated_webp_is_rejected_before_public_variants_exist(): void
    {
        $animation = new Imagick;
        try {
            foreach (['red', 'blue'] as $color) {
                $frame = new Imagick;
                $frame->newImage(2, 2, $color);
                $frame->setImageFormat('webp');
                $frame->setImageDelay(10);
                $animation->addImage($frame);
                $frame->clear();
                $frame->destroy();
            }
            $animation->setImageFormat('webp');
            $bytes = $animation->getImagesBlob();
        } finally {
            $animation->clear();
            $animation->destroy();
        }

        try {
            app(SecureImageProcessor::class)->process($bytes, 'image/webp');
            $this->fail('Animated WebP must be rejected.');
        } catch (MediaProcessingException $exception) {
            $this->assertSame('animated_image_rejected', $exception->reasonCode);
            $this->assertTrue($exception->rejected);
        }
    }
}
