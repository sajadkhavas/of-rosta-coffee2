<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-batches --hours=48 --unfinished=72 --cancelled=72')
    ->dailyAt('03:15')
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('03:30')
    ->withoutOverlapping();
