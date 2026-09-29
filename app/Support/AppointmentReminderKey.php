<?php

namespace App\Support;

use App\Models\Appointment;
use InvalidArgumentException;

final class AppointmentReminderKey
{
    /**
     * Build a key representing one exact appointment schedule.
     *
     * If an appointment is later rescheduled, its key changes,
     * allowing one new reminder for the newly approved schedule.
     */
    public static function for(
        Appointment $appointment
    ): string {
        if ($appointment->id === null) {
            throw new InvalidArgumentException(
                'Appointment must be persisted before a reminder key can be created.'
            );
        }

        if (
            $appointment->appointment_date
            === null
        ) {
            throw new InvalidArgumentException(
                'Appointment date is unavailable.'
            );
        }

        if (
            ! is_string(
                $appointment->starts_at
            )
            || trim(
                $appointment->starts_at
            ) === ''
        ) {
            throw new InvalidArgumentException(
                'Appointment start time is unavailable.'
            );
        }

        $normalizedTime =
            preg_replace(
                '/[^0-9]/',
                '',
                $appointment->starts_at
            );

        return sprintf(
            'appointment-reminder:%d:%s:%s',
            $appointment->id,
            $appointment
                ->appointment_date
                ->format('Ymd'),
            $normalizedTime,
        );
    }
}