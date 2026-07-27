<?php

namespace App\Enums;

enum SubOrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PendingAcceptance = 'pending_acceptance';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Preparing = 'preparing';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';
}
