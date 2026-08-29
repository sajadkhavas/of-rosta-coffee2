<?php

namespace App\Enums;

enum CarrierEventType: string
{
    case AwaitingPickup = 'awaiting_pickup';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case PickupFailed = 'pickup_failed';
    case DeliveryFailed = 'delivery_failed';
    case Lost = 'lost';
    case Damaged = 'damaged';
    case RequiresReview = 'requires_review';
}
