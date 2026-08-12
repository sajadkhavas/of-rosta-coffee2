<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsProvider;
use App\Exceptions\NotificationDeliveryUnavailable;
use App\Services\Notifications\Providers\DisabledSmsProvider;
use App\Services\Notifications\Providers\KavenegarSmsProvider;
use App\Services\Notifications\Providers\TestingSmsProvider;
use App\Services\Kavenegar\KavenegarClient;

final class SmsProviderManager
{
    public function current(): SmsProvider
    {
        return match (strtolower(trim((string) config('rosta.notifications.sms_provider', 'disabled')))) {
            'disabled' => app(DisabledSmsProvider::class),
            'testing' => app(TestingSmsProvider::class),
            'kavenegar' => app(KavenegarSmsProvider::class),
            default => throw new NotificationDeliveryUnavailable(
                'provider_unknown',
            ),
        };
    }

    public function ready(): bool
    {
        if (! config('rosta.notifications.enabled', false)) {
            return false;
        }

        $provider = strtolower(trim((string) config('rosta.notifications.sms_provider', 'disabled')));

        return match ($provider) {
            'testing' => app()->environment('testing'),
            'kavenegar' => app(KavenegarClient::class)->isAvailable()
                && trim((string) config('rosta.notifications.kavenegar.sender')) !== '',
            default => false,
        };
    }
}
