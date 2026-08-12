<?php

namespace App\Jobs;

use App\Services\Catalog\MediaUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ProcessMediaUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $uploadId,
    ) {
        $this->onQueue('media');
        $this->timeout = max(5, (int) ceil(
            (int) config('rosta.media_uploads.processing_timeout_ms', 15_000) / 1000,
        ) + 5);
    }

    public function handle(MediaUploadService $uploads): void
    {
        $uploads->process($this->uploadId);
    }

    public function failed(?Throwable $exception): void
    {
        try {
            app(MediaUploadService::class)->recordQueueFailure($this->uploadId);
        } catch (Throwable) {
            // Queue failure hooks must not reveal storage or decoder details.
        }
    }
}
