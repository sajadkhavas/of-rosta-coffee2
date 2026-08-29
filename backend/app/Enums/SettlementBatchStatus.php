<?php

namespace App\Enums;

enum SettlementBatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case RequiresReview = 'requires_review';
    case Paid = 'paid';
    case Failed = 'failed';
}
