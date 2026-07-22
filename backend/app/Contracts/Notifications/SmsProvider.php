<?php

namespace App\Contracts\Notifications;

interface SmsProvider
{
    public function name(): string;

    public function send(
        string $destination,
        string $message,
        ?string $providerTemplate = null,
    ): ?string;
}
