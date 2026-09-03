<?php

namespace App\Providers;

use App\Models\RefundAttempt;
use App\Observers\RefundAttemptObserver;
use Illuminate\Support\ServiceProvider;

final class GrowthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RefundAttempt::observe(RefundAttemptObserver::class);
    }
}
