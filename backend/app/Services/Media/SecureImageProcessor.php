<?php

namespace App\Services\Media;

use App\Exceptions\MediaProcessingException;
use Imagick;
use ImagickException;

final class SecureImageProcessor
{
    /**
     * @return array{
     *     detected_mime: string,
     *     width: int,
     *     height: int,
     *     blur_data_url: string,
     *     variants: list<array{width: int, height: int, format: string, extension: string, mime: string, bytes: string, checksum_sha256: string}>
     * }
     */
    public function process(string $bytes, string $expectedMime): array
    {
        $this->assertRuntime();
        $startedAt = hrtime(true);
        $detectedMime = $this->detectMime($bytes);
        if ($detectedMime !== $expectedMime) {
            throw new MediaProcessingException('mime_magic_mismatch', rejected: true);
        }

        $this->applyResourceLimits();
        $image = new Imagick;

        try {
            $image->pingImageBlob($bytes);
            if ($image->getNumberImages() !== 1) {
                throw new MediaProcessingException('animated_image_rejected', rejected: true);
            }

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $this->assertDimensions($width, $height);
            $this->assertElapsed($startedAt);

            $image->clear();
            $image->readImageBlob($bytes);
            if ($image->getNumberImages() !== 1) {
                throw new MediaProcessingException('animated_image_rejected', rejected: true);
            }

            $image->setIteratorIndex(0);
            $this->orient($image);
            $image->stripImage();
            $image->setImagePage(0, 0, 0, 0);
            $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $this->assertDimensions($width, $height);

            $blur = $this->blurPlaceholder($image);
            try {
                $variants = $this->variants($image, $startedAt);
            } catch (ImagickException) {
                throw new MediaProcessingException('variant_encode_failed', rejected: false);
            }
        } catch (MediaProcessingException $exception) {
            throw $exception;
        } catch (ImagickException) {
            throw new MediaProcessingException('image_decode_failed', rejected: true);
        } finally {
            $image->clear();
            $image->destroy();
        }

        return [
            'detected_mime' => $detectedMime,
            'width' => $width,
            'height' => $height,
            'blur_data_url' => $blur,
            'variants' => $variants,
        ];
    }

    public function isAvailable(): bool
    {
        if (! extension_loaded('imagick') || ! class_exists(Imagick::class)) {
            return false;
        }

        $formats = array_map('strtoupper', Imagick::queryFormats());

        return in_array('JPEG', $formats, true)
            && in_array('WEBP', $formats, true)
            && in_array('AVIF', $formats, true);
    }

    private function assertRuntime(): void
    {
        if (! $this->isAvailable()) {
            throw new MediaProcessingException('image_runtime_unavailable', rejected: false);
        }
    }

    private function applyResourceLimits(): void
    {
        $memoryBytes = max(16, (int) config('rosta.media_uploads.memory_limit_mb', 128)) * 1024 * 1024;
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, $memoryBytes);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, $memoryBytes);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_DISK, $memoryBytes);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
    }

    private function detectMime(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        if (
            strlen($bytes) >= 12
            && substr($bytes, 4, 4) === 'ftyp'
            && in_array(substr($bytes, 8, 4), ['avif', 'avis'], true)
        ) {
            return 'image/avif';
        }

        throw new MediaProcessingException('image_magic_disallowed', rejected: true);
    }

    private function assertDimensions(int $width, int $height): void
    {
        $maxWidth = max(1, (int) config('rosta.media_uploads.max_width', 8000));
        $maxHeight = max(1, (int) config('rosta.media_uploads.max_height', 8000));
        $maxPixels = max(1, (int) config('rosta.media_uploads.max_pixels', 40_000_000));

        if (
            $width < 1
            || $height < 1
            || $width > $maxWidth
            || $height > $maxHeight
            || $width > intdiv($maxPixels, $height)
        ) {
            throw new MediaProcessingException('image_dimensions_exceeded', rejected: true);
        }
    }

    private function assertElapsed(int $startedAt): void
    {
        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;
        $timeoutMilliseconds = max(500, (int) config('rosta.media_uploads.processing_timeout_ms', 15_000));
        if ($elapsedMilliseconds > $timeoutMilliseconds) {
            throw new MediaProcessingException('image_processing_timeout', rejected: false);
        }
    }

    private function orient(Imagick $image): void
    {
        if (method_exists($image, 'autoOrient')) {
            $image->autoOrient();
        } else {
            $image->autoOrientImage();
        }
        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    private function blurPlaceholder(Imagick $source): string
    {
        $blur = clone $source;
        try {
            $blur->thumbnailImage(32, 32, true, true);
            $blur = $this->flattenForJpeg($blur);
            $blur->stripImage();
            $blur->setImageFormat('jpeg');
            $blur->setImageCompressionQuality(30);
            $blob = $blur->getImageBlob();

            return 'data:image/jpeg;base64,'.base64_encode($blob);
        } finally {
            $blur->clear();
            $blur->destroy();
        }
    }

    /**
     * @return list<array{width: int, height: int, format: string, extension: string, mime: string, bytes: string, checksum_sha256: string}>
     */
    private function variants(Imagick $source, int $startedAt): array
    {
        $configured = (array) config('rosta.media_uploads.variant_widths', [320, 640, 1280]);
        $widths = array_values(array_unique(array_filter(
            array_map(static fn (mixed $width): int => (int) $width, $configured),
            static fn (int $width): bool => $width > 0,
        )));
        $widths[] = $source->getImageWidth();
        $widths = array_values(array_unique(array_filter(
            $widths,
            static fn (int $width): bool => $width <= $source->getImageWidth(),
        )));
        rsort($widths, SORT_NUMERIC);

        $formats = [
            ['format' => 'jpeg', 'extension' => 'jpg', 'mime' => 'image/jpeg', 'quality' => 82],
            ['format' => 'webp', 'extension' => 'webp', 'mime' => 'image/webp', 'quality' => 80],
            ['format' => 'avif', 'extension' => 'avif', 'mime' => 'image/avif', 'quality' => 55],
        ];
        $variants = [];

        foreach ($formats as $format) {
            foreach ($widths as $width) {
                $this->assertElapsed($startedAt);
                $variant = clone $source;
                try {
                    if ($variant->getImageWidth() !== $width) {
                        $variant->thumbnailImage($width, 0, true);
                    }
                    if ($format['format'] === 'jpeg') {
                        $variant = $this->flattenForJpeg($variant);
                    }
                    $variant->stripImage();
                    $variant->setImagePage(0, 0, 0, 0);
                    $variant->setImageDepth(8);
                    $variant->setFormat($format['format']);
                    $variant->setImageFormat($format['format']);
                    $variant->setImageCompressionQuality($format['quality']);
                    $blob = $variant->getImagesBlob();
                    if ($blob === '') {
                        throw new MediaProcessingException('variant_encode_failed', rejected: false);
                    }
                    $variants[] = [
                        'width' => $variant->getImageWidth(),
                        'height' => $variant->getImageHeight(),
                        'format' => $format['format'],
                        'extension' => $format['extension'],
                        'mime' => $format['mime'],
                        'bytes' => $blob,
                        'checksum_sha256' => hash('sha256', $blob),
                    ];
                } finally {
                    $variant->clear();
                    $variant->destroy();
                }
            }
        }

        return $variants;
    }

    private function flattenForJpeg(Imagick $source): Imagick
    {
        $source->setImageBackgroundColor('white');
        $flattened = $source->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        if ($flattened !== $source) {
            $source->clear();
            $source->destroy();
        }

        return $flattened;
    }
}
