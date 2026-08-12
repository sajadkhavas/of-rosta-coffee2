<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\Notifications\SmsProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TestingSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'testing';
    }

    public function send(
        string $destination,
        string $message,
        ?string $providerTemplate = null,
    ): string {
        $messageId = 'TEST-SMS-'.Str::upper(Str::random(20));
        Log::info('Rosta testing SMS dispatched.', [
            'message_id' => $messageId,
            'destination_suffix' => substr($destination, -4),
            'template_present' => $providerTemplate !== null,
        ]);

        return $messageId;
    }
}
