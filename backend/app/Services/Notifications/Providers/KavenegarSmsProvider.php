<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\Notifications\SmsProvider;
use App\Exceptions\NotificationDeliveryUnavailable;
use Illuminate\Support\Facades\Http;
use Throwable;

final class KavenegarSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'kavenegar';
    }

    public function send(
        string $destination,
        string $message,
        ?string $providerTemplate = null,
    ): ?string {
        $apiKey = trim((string) config('rosta.notifications.kavenegar.api_key'));
        $baseUrl = rtrim((string) config('rosta.notifications.kavenegar.base_url'), '/');
        $sender = trim((string) config('rosta.notifications.kavenegar.sender'));

        if ($apiKey === '') {
            throw new NotificationDeliveryUnavailable;
        }

        $payload = [
            'receptor' => $destination,
            'message' => $message,
        ];
        if ($sender !== '') {
            $payload['sender'] = $sender;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(max(1, (int) config('rosta.notifications.timeout_seconds', 8)))
                ->retry(2, 250, throw: false)
                ->get("{$baseUrl}/{$apiKey}/sms/send.json", $payload)
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw new NotificationDeliveryUnavailable(previous: $exception);
        }

        $messageId = data_get($response, 'entries.0.messageid');

        return $messageId === null ? null : (string) $messageId;
    }
}
