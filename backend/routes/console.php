<?php

use App\Enums\IdempotencyStatus;
use App\Models\CheckoutQuote;
use App\Models\OrderIdempotencyKey;
use App\Services\Checkout\ReservationExpiryService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(static function (): void {
    app(ReservationExpiryService::class)->expireDue();
})
    ->name('rosta.checkout.expire-reservations')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('notifications:dispatch --limit=100')
    ->name('rosta.notifications.dispatch')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('media:expire-upload-intents --limit=500')
    ->name('rosta.media.expire-upload-intents')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::call(static function (): void {
    CheckoutQuote::query()
        ->where('expires_at', '<', now()->subHour())
        ->whereDoesntHave('order')
        ->delete();

    OrderIdempotencyKey::query()
        ->where('expires_at', '<', now())
        ->where('status', '!=', IdempotencyStatus::Processing->value)
        ->delete();
})
    ->name('rosta.checkout.prune-expired-state')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('queue:prune-batches --hours=48 --unfinished=72 --cancelled=72')
    ->dailyAt('03:15')
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('03:30')
    ->withoutOverlapping();
