<?php

namespace App\Enums;

enum LedgerAccount: string
{
    case GatewayClearing = 'gateway_clearing';
    case PlatformRevenue = 'platform_revenue';
    case SellerPayable = 'seller_payable';
    case GatewayExpense = 'gateway_expense';
    case SettlementClearing = 'settlement_clearing';
}
