<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\Notifications\SmsProvider;
use App\Exceptions\NotificationDeliveryUnavailable;

final class DisabledSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'disabled';
    }

    public function send(
        string $destination,
        string $message,
        ?string $providerTemplate = null,
    ): ?string {
        throw new NotificationDeliveryUnavailable('provider_disabled');
    }
}
