<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Services\Appointments\ShopBreakPeriodAvailabilityGuard;

class RescheduleRequestBreakPeriodObserver
{
    public function __construct(
        private readonly ShopBreakPeriodAvailabilityGuard $breakPeriodGuard
    ) {}

    /**
     * New reschedule requests must respect recurring shop breaks.
     */
    public function creating(
        RescheduleRequest $rescheduleRequest
    ): void {
        $this->protectRequestedTime(
            $rescheduleRequest
        );
    }

    /**
     * Protect any future edits to the requested timing.
     *
     * Status-only changes remain unaffected.
     */
    public function updating(
        RescheduleRequest $rescheduleRequest
    ): void {
        if (
            ! $rescheduleRequest->isDirty(
                'requested_date'
            )
            &&
            ! $rescheduleRequest->isDirty(
                'requested_starts_at'
            )
            &&
            ! $rescheduleRequest->isDirty(
                'requested_ends_at'
            )
            &&
            ! $rescheduleRequest->isDirty(
                'appointment_id'
            )
        ) {
            return;
        }

        $this->protectRequestedTime(
            $rescheduleRequest
        );
    }

    private function protectRequestedTime(
        RescheduleRequest $rescheduleRequest
    ): void {
        if (
            $rescheduleRequest->appointment_id === null
            ||
            $rescheduleRequest->requested_date === null
            ||
            $rescheduleRequest->requested_starts_at === null
            ||
            $rescheduleRequest->requested_ends_at === null
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve Shop Through Original Appointment
        |--------------------------------------------------------------------------
        |
        | The client cannot provide or manipulate the shop used for this check.
        |
        */

        $appointment =
            Appointment::query()
                ->select([
                    'id',
                    'shop_id',
                ])
                ->whereKey(
                    $rescheduleRequest
                        ->appointment_id
                )
                ->firstOrFail();

        $this
            ->breakPeriodGuard
            ->assertMutationDoesNotOverlapBreak(
                shopId:
                    (int) $appointment->shop_id,

                date:
                    $rescheduleRequest
                        ->requested_date,

                startsAt:
                    (string) $rescheduleRequest
                        ->requested_starts_at,

                endsAt:
                    (string) $rescheduleRequest
                        ->requested_ends_at,

                field:
                    'requested_starts_at',
            );
    }
}