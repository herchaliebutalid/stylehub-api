<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\Appointments\ShopBreakPeriodAvailabilityGuard;

class AppointmentBreakPeriodObserver
{
    public function __construct(
        private readonly ShopBreakPeriodAvailabilityGuard $breakPeriodGuard
    ) {}

    /**
     * Every new appointment must respect recurring Shop Owner breaks.
     */
    public function creating(
        Appointment $appointment
    ): void {
        $this->protectAppointmentMutation(
            $appointment
        );
    }

    /**
     * Protect appointment moves while allowing ordinary status-only changes.
     *
     * Existing appointments therefore remain manageable if the owner creates
     * a break after the appointment was originally booked.
     */
    public function updating(
        Appointment $appointment
    ): void {
        if (
            ! $appointment->isDirty(
                'appointment_date'
            )
            &&
            ! $appointment->isDirty(
                'starts_at'
            )
            &&
            ! $appointment->isDirty(
                'ends_at'
            )
            &&
            ! $appointment->isDirty(
                'shop_id'
            )
        ) {
            return;
        }

        $this->protectAppointmentMutation(
            $appointment
        );
    }

    private function protectAppointmentMutation(
        Appointment $appointment
    ): void {
        if (
            $appointment->shop_id === null
            ||
            $appointment->appointment_date === null
            ||
            $appointment->starts_at === null
            ||
            $appointment->ends_at === null
        ) {
            return;
        }

        $this
            ->breakPeriodGuard
            ->assertMutationDoesNotOverlapBreak(
                shopId:
                    (int) $appointment->shop_id,

                date:
                    $appointment->appointment_date,

                startsAt:
                    (string) $appointment->starts_at,

                endsAt:
                    (string) $appointment->ends_at,

                field:
                    'starts_at',
            );
    }
}