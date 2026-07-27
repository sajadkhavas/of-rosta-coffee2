<?php

namespace App\Enums;

enum SubOrderAcceptanceStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingRoasteryAcceptance = 'awaiting_roastery_acceptance';
    case Accepted = 'accepted';
    case RejectedByRoastery = 'rejected_by_roastery';
    case CancelledByCustomer = 'cancelled_by_customer';

    public function customerCancellable(): bool
    {
        return in_array($this, [self::AwaitingPayment, self::AwaitingRoasteryAcceptance], true);
    }
}
