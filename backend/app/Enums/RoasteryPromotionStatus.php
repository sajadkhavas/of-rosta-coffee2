<?php

namespace App\Enums;

enum RoasteryPromotionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Paused = 'paused';
    case Expired = 'expired';
}
