<?php

namespace App\Enums;

enum RoasteryStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
}
