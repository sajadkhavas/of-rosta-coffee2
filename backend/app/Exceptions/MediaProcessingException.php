<?php

namespace App\Exceptions;

use RuntimeException;

final class MediaProcessingException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        public readonly bool $rejected,
    ) {
        parent::__construct('Media processing failed.');
    }
}
