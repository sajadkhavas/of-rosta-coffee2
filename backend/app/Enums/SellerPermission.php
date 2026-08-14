<?php

namespace App\Enums;

enum SellerPermission: string
{
    case WorkspaceRead = 'workspace.read';
    case OrganizationRead = 'organization.read';
    case OrganizationManage = 'organization.manage';
    case CatalogRead = 'catalog.read';
    case CatalogWrite = 'catalog.write';
    case InventoryRead = 'inventory.read';
    case InventoryWrite = 'inventory.write';
    case OrdersRead = 'orders.read';
    case FulfillmentWrite = 'fulfillment.write';
    case FinanceRead = 'finance.read';
    case AvailabilityRead = 'availability.read';
    case AvailabilityWrite = 'availability.write';
    case PromotionRead = 'promotion.read';
    case PromotionWrite = 'promotion.write';
}
