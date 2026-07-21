<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
