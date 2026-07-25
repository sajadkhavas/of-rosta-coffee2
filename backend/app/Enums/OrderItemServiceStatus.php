<?php

namespace App\Enums;

enum OrderItemServiceStatus: string
{
    case Requested = 'requested';
    case WaitingProvider = 'waiting_provider';
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case RequiresReview = 'requires_review';
    case Cancelled = 'cancelled';
}
