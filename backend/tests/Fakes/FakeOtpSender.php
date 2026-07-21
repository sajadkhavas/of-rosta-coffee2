<?php

namespace Tests\Fakes;

use App\Contracts\OtpSender;

final class FakeOtpSender implements OtpSender
{
    /**
     * @var list<array{mobile: string, code: string, purpose: string, challenge_id: string}>
     */
    public array $messages = [];

    public function isAvailable(): bool
    {
        return true;
    }

    public function send(
        string $mobile,
        string $code,
        string $purpose,
        string $challengeId,
    ): void {
        $this->messages[] = [
            'mobile' => $mobile,
            'code' => $code,
            'purpose' => $purpose,
            'challenge_id' => $challengeId,
        ];
    }

    /**
     * @return array{mobile: string, code: string, purpose: string, challenge_id: string}
     */
    public function latest(): array
    {
        $message = end($this->messages);
        if (! is_array($message)) {
            throw new \RuntimeException('No OTP message was captured.');
        }

        return $message;
    }
}
