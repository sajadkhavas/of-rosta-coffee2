<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Consumed = 'consumed';
    case Expired = 'expired';
}
