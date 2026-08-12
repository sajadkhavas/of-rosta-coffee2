<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\Notifications\SmsProvider;
use App\Exceptions\KavenegarDeliveryException;
use App\Exceptions\NotificationDeliveryUnavailable;
use App\Services\Kavenegar\KavenegarClient;
use App\Support\IranMobile;

final class KavenegarSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly KavenegarClient $client,
    ) {}

    public function name(): string
    {
        return 'kavenegar';
    }

    public function send(
        string $destination,
        string $message,
        ?string $providerTemplate = null,
    ): string {
        $sender = trim((string) config('rosta.notifications.kavenegar.sender'));
        $normalizedDestination = IranMobile::normalize($destination);

        if (
            ! $this->client->isConfigured()
            || ! IranMobile::isValid($normalizedDestination)
            || $sender === ''
            || mb_strlen($message) > 2000
            || trim($message) === ''
        ) {
            throw new NotificationDeliveryUnavailable('provider_configuration_or_payload_invalid');
        }

        if (! $this->client->isAvailable()) {
            throw new NotificationDeliveryUnavailable(
                'circuit_open',
                retryable: true,
                retryAfterSeconds: $this->client->circuitRetryAfterSeconds(),
            );
        }

        try {
            return $this->client->sendMessage(
                $normalizedDestination,
                $message,
                $sender,
            );
        } catch (KavenegarDeliveryException $exception) {
            throw new NotificationDeliveryUnavailable(
                $exception->reasonCode,
                $exception->retryable,
                $exception->ambiguous,
                $exception->retryAfterSeconds,
            );
        }
    }
}
