<?php

namespace App\Exceptions;

use RuntimeException;

final class NotificationDeliveryUnavailable extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode = 'sms_unavailable',
        public readonly bool $retryable = false,
        public readonly bool $ambiguous = false,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct('SMS delivery is unavailable.');
    }
}
