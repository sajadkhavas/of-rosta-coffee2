<?php

namespace App\Enums;

enum ReconciliationStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
}
