<?php

namespace App\Enums;

enum ReconciliationStatus: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Resolved, self::Dismissed], true);
    }
}
