<?php

namespace App\Providers;

use App\Models\PaymentAttempt;
use App\Observers\PaymentAttemptObserver;
use Illuminate\Support\ServiceProvider;

final class FinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PaymentAttempt::observe(PaymentAttemptObserver::class);
    }
}
