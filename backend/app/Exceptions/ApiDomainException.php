<?php

namespace App\Exceptions;

use RuntimeException;

final class ApiDomainException extends RuntimeException
{
    /**
     * @param  array<string, string|array<int, string>>  $fields
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $fields = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }
}
