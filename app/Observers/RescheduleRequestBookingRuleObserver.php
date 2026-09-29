<?php

namespace App\Observers;

use App\Enums\ShopBookingRuleOperation;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Models\Shop;
use App\Services\Appointments\ShopBookingRuleEnforcer;
use App\Services\Appointments\ShopBookingRuleRuntime;
use Carbon\CarbonImmutable;

class RescheduleRequestBookingRuleObserver
{
    public function __construct(
        private readonly ShopBookingRuleRuntime $runtime,
        private readonly ShopBookingRuleEnforcer $enforcer
    ) {}

    public function creating(
        RescheduleRequest $request
    ): void {
        $appointment =
            Appointment::query()
                ->whereKey(
                    $request->appointment_id
                )
                ->first();

        if ($appointment === null) {
            return;
        }

        if (
            ! $this->runtime->matches(
                (int) $appointment->shop_id,
                ShopBookingRuleOperation::CustomerRescheduleRequest
            )
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Original Appointment Cutoff
        |--------------------------------------------------------------------------
        */

        $this->enforcer
            ->assertCustomerRescheduleAllowed(
                $appointment
            );

        /*
        |--------------------------------------------------------------------------
        | Requested Replacement Slot
        |--------------------------------------------------------------------------
        */

        $shop =
            Shop::query()
                ->whereKey(
                    $appointment->shop_id
                )
                ->firstOrFail();

        $requestedStart =
            CarbonImmutable::parse(
                $request
                    ->requested_date
                    ->toDateString()
                .' '
                .$request
                    ->requested_starts_at,
                config(
                    'app.timezone',
                    'Asia/Manila'
                )
            );

        $this->enforcer
            ->assertTargetSlotAllowed(
                shop:
                    $shop,

                startsAt:
                    $requestedStart,

                dateField:
                    'requested_date',

                timeField:
                    'requested_starts_at',
            );
    }
}