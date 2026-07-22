<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationOutboxService;
use Illuminate\Console\Command;

final class DispatchNotificationOutbox extends Command
{
    protected $signature = 'notifications:dispatch {--limit=100 : Maximum records to inspect}';

    protected $description = 'Dispatch pending Rosta notification outbox records';

    public function handle(NotificationOutboxService $outbox): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $sent = $outbox->dispatchPending($limit);
        $this->info("Dispatched {$sent} notification(s).");

        return self::SUCCESS;
    }
}
