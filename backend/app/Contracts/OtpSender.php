<?php

namespace App\Contracts;

interface OtpSender
{
    public function isAvailable(): bool;

    public function send(
        string $mobile,
        string $code,
        string $purpose,
        string $challengeId,
    ): void;
}
