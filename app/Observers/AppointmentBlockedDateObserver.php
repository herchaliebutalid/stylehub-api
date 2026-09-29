<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\Appointments\ShopBlockedDateAvailabilityGuard;

class AppointmentBlockedDateObserver
{
    public function __construct(
        private readonly ShopBlockedDateAvailabilityGuard $blockedDateGuard
    ) {}

    /**
     * Every new appointment must respect blocked dates.
     */
    public function creating(
        Appointment $appointment
    ): void {
        $this->protectAppointmentMutation(
            $appointment
        );
    }

    /**
     * Protect appointment moves without preventing ordinary status changes.
     *
     * For example:
     *
     * confirmed -> cancelled
     *
     * is still allowed for an appointment that existed before the owner
     * blocked that date.
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
        ) {
            return;
        }

        $this
            ->blockedDateGuard
            ->assertMutationDateIsNotBlocked(
                shopId:
                    (int) $appointment->shop_id,

                date:
                    $appointment->appointment_date,

                field:
                    'appointment_date',
            );
    }
}