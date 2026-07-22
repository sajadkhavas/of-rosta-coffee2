<?php

namespace App\Enums;

enum QuotePurpose: string
{
    case CartValidation = 'cart_validation';
    case Checkout = 'checkout';
}
