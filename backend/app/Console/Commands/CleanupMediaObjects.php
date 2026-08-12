<?php

namespace App\Console\Commands;

use App\Services\Catalog\MediaUploadService;
use Illuminate\Console\Command;

final class CleanupMediaObjects extends Command
{
    protected $signature = 'media:cleanup-terminal {--limit=500}';

    protected $description = 'Delete retained private sources for ready and terminal media intents';

    public function handle(MediaUploadService $uploads): int
    {
        $count = $uploads->cleanupTerminal((int) $this->option('limit'));
        $this->info("Cleaned {$count} retained media source object(s).");

        return self::SUCCESS;
    }
}
