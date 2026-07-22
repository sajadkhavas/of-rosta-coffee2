<?php

namespace App\Console\Commands;

use App\Services\Catalog\MediaUploadService;
use Illuminate\Console\Command;

final class ExpireMediaUploadIntents extends Command
{
    protected $signature = 'media:expire-upload-intents {--limit=500 : Maximum intents to inspect}';

    protected $description = 'Expire abandoned Rosta media upload intents and remove stale objects';

    public function handle(MediaUploadService $uploads): int
    {
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $expired = $uploads->expireDue($limit);
        $this->info("Expired {$expired} media upload intent(s).");

        return self::SUCCESS;
    }
}
