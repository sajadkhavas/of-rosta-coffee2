<?php

namespace App\Enums;

enum FulfillmentIncidentResolution: string
{
    case ResumeFulfillment = 'resume_fulfillment';
    case CancelAndRefund = 'cancel_and_refund';
}
