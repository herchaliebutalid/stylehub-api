<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Services\Appointments\ShopBlockedDateAvailabilityGuard;

class RescheduleRequestBlockedDateObserver
{
    public function __construct(
        private readonly ShopBlockedDateAvailabilityGuard $blockedDateGuard
    ) {}

    /**
     * A customer cannot create a reschedule request for a blocked date.
     */
    public function creating(
        RescheduleRequest $rescheduleRequest
    ): void {
        $this->protectRequestedDate(
            $rescheduleRequest
        );
    }

    /**
     * Defensive protection if requested timing is ever editable in the future.
     *
     * Status-only changes such as:
     *
     * pending -> rejected
     * pending -> withdrawn
     *
     * are deliberately unaffected.
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
                'appointment_id'
            )
        ) {
            return;
        }

        $this->protectRequestedDate(
            $rescheduleRequest
        );
    }

    private function protectRequestedDate(
        RescheduleRequest $rescheduleRequest
    ): void {
        if (
            $rescheduleRequest->appointment_id
                ===
                null
            ||
            $rescheduleRequest->requested_date
                ===
                null
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve Shop From Appointment
        |--------------------------------------------------------------------------
        |
        | The client never gets to provide the shop ownership used for this
        | security decision.
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
            ->blockedDateGuard
            ->assertMutationDateIsNotBlocked(
                shopId:
                    (int) $appointment->shop_id,

                date:
                    $rescheduleRequest
                        ->requested_date,

                field:
                    'requested_date',
            );
    }
}