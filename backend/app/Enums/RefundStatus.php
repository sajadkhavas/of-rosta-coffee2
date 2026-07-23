<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RequiresReview = 'requires_review';

    public function reservesAmount(): bool
    {
        return ! in_array($this, [self::Failed, self::Cancelled], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled], true);
    }
}
