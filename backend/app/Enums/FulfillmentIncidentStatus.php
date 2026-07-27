<?php

namespace App\Enums;

enum FulfillmentIncidentStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
