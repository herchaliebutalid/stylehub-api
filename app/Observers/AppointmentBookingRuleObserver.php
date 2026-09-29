<?php

namespace App\Observers;

use App\Enums\AppointmentStatus;
use App\Enums\ShopBookingRuleOperation;
use App\Models\Appointment;
use App\Models\Shop;
use App\Services\Appointments\ShopBookingRuleEnforcer;
use App\Services\Appointments\ShopBookingRuleRuntime;
use Carbon\CarbonImmutable;

class AppointmentBookingRuleObserver
{
    public function __construct(
        private readonly ShopBookingRuleRuntime $runtime,
        private readonly ShopBookingRuleEnforcer $enforcer
    ) {}

    /**
     * Final booking-write protection.
     */
    public function creating(
        Appointment $appointment
    ): void {
        if (
            ! $this->runtime->matches(
                (int) $appointment->shop_id,
                ShopBookingRuleOperation::CustomerBooking
            )
        ) {
            return;
        }

        $this->enforcer
            ->assertTargetSlotAllowed(
                shop:
                    $this->shop(
                        $appointment
                    ),

                startsAt:
                    $this->appointmentStart(
                        $appointment
                    ),

                dateField:
                    'appointment_date',

                timeField:
                    'starts_at',
            );
    }

    public function updating(
        Appointment $appointment
    ): void {
        $shopId =
            (int) $appointment
                ->shop_id;

        /*
        |--------------------------------------------------------------------------
        | Customer Cancellation Cutoff
        |--------------------------------------------------------------------------
        */

        if (
            $this->runtime->matches(
                $shopId,
                ShopBookingRuleOperation::CustomerCancellation
            )
            &&
            $appointment->isDirty(
                'status'
            )
            &&
            $appointment->status
                === AppointmentStatus::Cancelled
        ) {
            $this->enforcer
                ->assertCustomerCancellationAllowed(
                    $appointment
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Owner Reschedule Approval Recheck
        |--------------------------------------------------------------------------
        |
        | A pending request may have been submitted when the shop had different
        | booking rules. The current rules are authoritative when approving it.
        |
        */

        if (
            $this->runtime->matches(
                $shopId,
                ShopBookingRuleOperation::ShopOwnerRescheduleApproval
            )
            &&
            (
                $appointment->isDirty(
                    'appointment_date'
                )
                ||
                $appointment->isDirty(
                    'starts_at'
                )
            )
        ) {
            $this->enforcer
                ->assertTargetSlotAllowed(
                    shop:
                        $this->shop(
                            $appointment
                        ),

                    startsAt:
                        $this->appointmentStart(
                            $appointment
                        ),

                    dateField:
                        'requested_date',

                    timeField:
                        'requested_starts_at',
                );
        }
    }

    private function shop(
        Appointment $appointment
    ): Shop {
        return Shop::query()
            ->whereKey(
                $appointment->shop_id
            )
            ->firstOrFail();
    }

    private function appointmentStart(
        Appointment $appointment
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            $appointment
                ->appointment_date
                ->toDateString()
            .' '
            .$appointment->starts_at,
            config(
                'app.timezone',
                'Asia/Manila'
            )
        );
    }
}