<?php

namespace App\Enums;

enum AuditEvent: string
{
    /*
    |--------------------------------------------------------------------------
    | Shop Administration
    |--------------------------------------------------------------------------
    */

    case ShopApproved =
        'shop_approved';

    case ShopRejected =
        'shop_rejected';

    case ShopAvailabilityUpdated =
        'shop_availability_updated';

    case ShopBookingRulesUpdated =
        'shop_booking_rules_updated';

    case ShopSuspended =
        'shop_suspended';

    case ShopReactivated =
        'shop_reactivated';

    /*
    |--------------------------------------------------------------------------
    | User Administration
    |--------------------------------------------------------------------------
    */

    case UserSuspended =
        'user_suspended';

    case UserReactivated =
        'user_reactivated';

    /*
    |--------------------------------------------------------------------------
    | Administrator Account
    |--------------------------------------------------------------------------
    */

    case AdminAccountUpdated =
        'admin_account_updated';

    case AdminPasswordChanged =
        'admin_password_changed';

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    case ServiceCreated =
        'service_created';

    case ServiceUpdated =
        'service_updated';

    case ServiceDeleted =
        'service_deleted';

    /*
    |--------------------------------------------------------------------------
    | Shop Schedule
    |--------------------------------------------------------------------------
    */

    case ShopScheduleUpdated =
        'shop_schedule_updated';

    /*
    |--------------------------------------------------------------------------
    | Appointments
    |--------------------------------------------------------------------------
    */

    case AppointmentBooked =
        'appointment_booked';

    case AppointmentConfirmed =
        'appointment_confirmed';

    case AppointmentDeclined =
        'appointment_declined';

    case AppointmentCancelled =
        'appointment_cancelled';

    case AppointmentCompleted =
        'appointment_completed';

    case AppointmentNoShow =
        'appointment_no_show';

    /*
    |--------------------------------------------------------------------------
    | Reschedule Requests
    |--------------------------------------------------------------------------
    */

    case RescheduleRequested =
        'reschedule_requested';

    case RescheduleApproved =
        'reschedule_approved';

    case RescheduleRejected =
        'reschedule_rejected';

    case RescheduleWithdrawn =
        'reschedule_withdrawn';

    /*
    |--------------------------------------------------------------------------
    | Promotions
    |--------------------------------------------------------------------------
    */

    case PromoCreated =
        'promo_created';

    case PromoUpdated =
        'promo_updated';

    case PromoDeleted =
        'promo_deleted';

    /*
    |--------------------------------------------------------------------------
    | Feedback
    |--------------------------------------------------------------------------
    */

    case FeedbackSubmitted =
        'feedback_submitted';

    /*
    |--------------------------------------------------------------------------
    | SMS Administration
    |--------------------------------------------------------------------------
    */

    case SmsRetryQueued =
        'sms_retry_queued';

    case SmsTemplateUpdated =
        'sms_template_updated';

    public function label(): string
    {
        return ucwords(
            str_replace(
                '_',
                ' ',
                $this->value
            )
        );
    }
}