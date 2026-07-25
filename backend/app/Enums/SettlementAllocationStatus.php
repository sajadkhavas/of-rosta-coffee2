<?php

namespace App\Enums;

enum SettlementAllocationStatus: string
{
    case Held = 'held';
    case Eligible = 'eligible';
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Reversed = 'reversed';
    case RequiresReview = 'requires_review';
}
