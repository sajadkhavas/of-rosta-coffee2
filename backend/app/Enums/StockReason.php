<?php

namespace App\Enums;

enum StockReason: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Correction = 'correction';
    case Damage = 'damage';
    case Expiry = 'expiry';
    case Return = 'return';
    case ReservationRelease = 'reservation_release';
}
