<?php

namespace App\Enums;

enum RoasteryMembershipRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Catalog = 'catalog';
    case Fulfillment = 'fulfillment';
    case Finance = 'finance';
    case Support = 'support';
}
