<?php

namespace App\Exceptions;

use RuntimeException;

final class KavenegarDeliveryException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        public readonly bool $retryable = false,
        public readonly bool $ambiguous = false,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct('Kavenegar delivery failed.');
    }
}
