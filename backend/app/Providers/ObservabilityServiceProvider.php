<?php

namespace App\Providers;

use App\Services\Observability\QueueTelemetry;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueueTelemetry::class);
    }

    public function boot(): void
    {
        Queue::before(function (JobProcessing $event): void {
            $this->app->make(QueueTelemetry::class)->processing($event);
        });

        Queue::after(function (JobProcessed $event): void {
            $this->app->make(QueueTelemetry::class)->processed($event);
        });

        Queue::failing(function (JobFailed $event): void {
            $this->app->make(QueueTelemetry::class)->failed($event);
        });
    }
}
