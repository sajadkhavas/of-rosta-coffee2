<?php

use App\Providers\AppServiceProvider;
use App\Providers\FinanceServiceProvider;
use App\Providers\ObservabilityServiceProvider;
use App\Providers\ProductionSafetyServiceProvider;
use App\Providers\QuizReviewServiceProvider;

return [
    AppServiceProvider::class,
    FinanceServiceProvider::class,
    ObservabilityServiceProvider::class,
    ProductionSafetyServiceProvider::class,
    QuizReviewServiceProvider::class,
];
