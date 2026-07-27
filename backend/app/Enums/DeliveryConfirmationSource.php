<?php

namespace App\Enums;

enum DeliveryConfirmationSource: string
{
    case Customer = 'customer';
    case Administrator = 'administrator';
    case Carrier = 'carrier';
}
