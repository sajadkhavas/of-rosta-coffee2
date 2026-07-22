<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class NotificationDeliveryUnavailable extends RuntimeException
{
    public function __construct(
        string $message = 'سرویس ارسال پیامک در دسترس نیست.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
