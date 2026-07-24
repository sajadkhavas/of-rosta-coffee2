<?php

use App\Providers\AppServiceProvider;
use App\Providers\FinanceServiceProvider;
use App\Providers\ProductionSafetyServiceProvider;

return [
    AppServiceProvider::class,
    FinanceServiceProvider::class,
    ProductionSafetyServiceProvider::class,
];
