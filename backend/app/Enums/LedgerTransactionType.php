<?php

namespace App\Enums;

enum LedgerTransactionType: string
{
    case Sale = 'sale';
    case Refund = 'refund';
    case GatewayFee = 'gateway_fee';
    case Settlement = 'settlement';
    case Adjustment = 'adjustment';
}
