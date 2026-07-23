<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class ProductionSafetyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! $this->app->environment('production')) {
                return;
            }

            $command = trim((string) $event->command);
            if ($command === 'db:seed' || str_starts_with($command, 'db:seed ')) {
                throw new RuntimeException(
                    'Production database seeding is forbidden. Publish catalog and content through the authenticated operational workspaces.',
                );
            }

            if ($command === 'migrate:fresh' || str_starts_with($command, 'migrate:fresh ')) {
                throw new RuntimeException('migrate:fresh is forbidden in production.');
            }
        });
    }
}
