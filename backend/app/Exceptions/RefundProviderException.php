<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class RefundProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $providerCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
