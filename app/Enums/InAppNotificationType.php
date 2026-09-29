<?php

namespace App\Enums;

enum InAppNotificationType: string
{
    case BookingReceived = 'booking_received';

    case AppointmentConfirmed = 'appointment_confirmed';

    case AppointmentDeclined = 'appointment_declined';

    case AppointmentCancelled = 'appointment_cancelled';

    case AppointmentCompleted = 'appointment_completed';

    case AppointmentNoShow = 'appointment_no_show';

    case AppointmentReminder = 'appointment_reminder';

    case RescheduleRequested = 'reschedule_requested';

    case RescheduleApproved = 'reschedule_approved';

    case RescheduleRejected = 'reschedule_rejected';

    case ReviewReceived = 'review_received';

    case PromoAvailable = 'promo_available';

    public function label(): string
    {
        return match ($this) {
            self::BookingReceived =>
                'New Appointment',

            self::AppointmentConfirmed =>
                'Appointment Confirmed',

            self::AppointmentDeclined =>
                'Appointment Declined',

            self::AppointmentCancelled =>
                'Appointment Cancelled',

            self::AppointmentCompleted =>
                'Appointment Completed',

            self::AppointmentNoShow =>
                'Appointment No-show',

            self::AppointmentReminder =>
                'Appointment Reminder',

            self::RescheduleRequested =>
                'New Reschedule Request',

            self::RescheduleApproved =>
                'Reschedule Approved',

            self::RescheduleRejected =>
                'Reschedule Rejected',

            self::ReviewReceived =>
                'New Review',

            self::PromoAvailable =>
                'New Promotion',
        };
    }
}