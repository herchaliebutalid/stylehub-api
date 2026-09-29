<?php

namespace App\Enums;

enum ShopBookingRuleOperation: string
{
    case Availability = 'availability';

    case CustomerBooking =
        'customer_booking';

    case CustomerCancellation =
        'customer_cancellation';

    case CustomerRescheduleRequest =
        'customer_reschedule_request';

    case ShopOwnerRescheduleApproval =
        'shop_owner_reschedule_approval';
}