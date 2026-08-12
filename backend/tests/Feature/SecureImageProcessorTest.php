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
            $source->newImage(30, 20, '#6f4e37');
            $source->setImageFormat('jpeg');
            $jpeg = $source->getImageBlob();
            $bytes = $this->withExifOrientation($jpeg, 6);
        } finally {
            $source->clear();
            $source->destroy();
        }

        $result = app(SecureImageProcessor::class)->process($bytes, 'image/jpeg');

        $this->assertSame(20, $result['width']);
        $this->assertSame(30, $result['height']);
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

    private function withExifOrientation(string $jpeg, int $orientation): string
    {
        $tiff = "II\x2A\x00\x08\x00\x00\x00"
            ."\x01\x00"
            ."\x12\x01\x03\x00\x01\x00\x00\x00"
            .pack('v', $orientation)."\x00\x00"
            ."\x00\x00\x00\x00";
        $exif = "Exif\x00\x00".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
