<?php

namespace App\Enums;

enum OtpDeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
