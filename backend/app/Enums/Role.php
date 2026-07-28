<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case RoasteryOwner = 'roastery_owner';
    case RoasteryManager = 'roastery_manager';
    case RoasteryStaff = 'roastery_staff';
    case Administrator = 'administrator';
    case Support = 'support';
    case Finance = 'finance';
    case HubOperator = 'hub_operator';
}
