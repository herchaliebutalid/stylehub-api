<?php

namespace App\Enums;

enum SmsNotificationType: string
{
    case BookingReceived = 'booking_received';

    case AppointmentConfirmed = 'appointment_confirmed';

    case AppointmentDeclined = 'appointment_declined';

    case AppointmentCancelled = 'appointment_cancelled';

    case RescheduleApproved = 'reschedule_approved';

    case RescheduleRejected = 'reschedule_rejected';

    case AppointmentReminder = 'appointment_reminder';

    /*
    |--------------------------------------------------------------------------
    | Controlled Semaphore Verification
    |--------------------------------------------------------------------------
    |
    | Used only by the explicit CLI test command.
    |
    | It is not tied to a customer appointment.
    |
    */

    case SystemTest = 'system_test';

    public function label(): string
    {
        return match ($this) {
            self::BookingReceived =>
                'Booking Received',

            self::AppointmentConfirmed =>
                'Appointment Confirmed',

            self::AppointmentDeclined =>
                'Appointment Declined',

            self::AppointmentCancelled =>
                'Appointment Cancelled',

            self::RescheduleApproved =>
                'Reschedule Approved',

            self::RescheduleRejected =>
                'Reschedule Rejected',

            self::AppointmentReminder =>
                'Appointment Reminder',

            self::SystemTest =>
                'System Test',
        };
    }
}