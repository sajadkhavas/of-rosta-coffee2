<?php

namespace App\Enums;

enum FulfillmentSlaStatus: string
{
    case NotStarted = 'not_started';
    case OnTrack = 'on_track';
    case Preparing = 'preparing';
    case ReadyToShip = 'ready_to_ship';
    case HandedOff = 'handed_off';
    case Breached = 'breached';
    case ExceptionOpen = 'exception_open';
    case ExceptionResolved = 'exception_resolved';
}
