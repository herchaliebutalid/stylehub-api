<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShopBookingRuleEnforcer
{
    public function __construct(
        private readonly ShopBookingRuleResolver $resolver
    ) {}

    /**
     * Enforce the shop's current rules against a proposed
     * appointment/reschedule starting time.
     */
    public function assertTargetSlotAllowed(
        Shop $shop,
        CarbonImmutable $startsAt,
        string $dateField,
        string $timeField
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Final Customer-Visibility / Suspension Guard
        |--------------------------------------------------------------------------
        |
        | Appointment and reschedule writes must use a shop that remains
        | customer-visible at the final model-write boundary.
        |
        | visibleToCustomers() protects against:
        |
        | - unapproved shops
        | - independently suspended shops
        | - suspended Shop Owner accounts
        |
        | This is important because the shop's state may change after the
        | customer originally loaded availability.
        |
        */

        $isCustomerVisible =
            Shop::query()
                ->visibleToCustomers()
                ->whereKey(
                    $shop->id
                )
                ->exists();

        if (
            ! $isCustomerVisible
        ) {
            throw ValidationException::withMessages([
                $dateField =>
                    'This shop is not currently available for appointments.',
            ]);
        }

        /*
         * Resolve again at the actual model-write boundary.
         *
         * This is intentionally not dependent only on the middleware's
         * earlier copy of the rules. A rule change occurring before the
         * transaction reaches its final write is therefore rechecked.
         */

        $rules =
            $this->resolver->forShop(
                $shop
            );

        $timezone =
            $this->timezone();

        $now =
            CarbonImmutable::now(
                $timezone
            );

        $selectedDate =
            $startsAt->startOfDay();

        $today =
            $now->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | Past Date
        |--------------------------------------------------------------------------
        */

        if (
            $selectedDate->lessThan(
                $today
            )
        ) {
            throw ValidationException::withMessages([
                $dateField =>
                    'The appointment date cannot be in the past.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Shop Booking Window
        |--------------------------------------------------------------------------
        */

        $latestDate =
            $today->addDays(
                $rules
                    ->booking_window_days
            );

        if (
            $selectedDate->greaterThan(
                $latestDate
            )
        ) {
            throw ValidationException::withMessages([
                $dateField =>
                    'The appointment date is outside the allowed booking window.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Minimum Lead Time
        |--------------------------------------------------------------------------
        */

        $minimumLeadTime =
            $rules
                ->minimum_lead_time_minutes;

        if (
            $minimumLeadTime > 0
            &&
            $startsAt->lessThan(
                $now->addMinutes(
                    $minimumLeadTime
                )
            )
        ) {
            throw ValidationException::withMessages([
                $timeField =>
                    sprintf(
                        'Appointments must be booked at least %d minutes in advance.',
                        $minimumLeadTime
                    ),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Slot Grid
        |--------------------------------------------------------------------------
        */

        $schedule =
            $shop
                ->schedules()
                ->where(
                    'day_of_week',
                    $startsAt
                        ->isoWeekday()
                )
                ->first();

        if (
            $schedule === null
            ||
            ! $schedule->isOpen()
            ||
            ! $schedule
                ->hasOperatingHours()
        ) {
            throw ValidationException::withMessages([
                $dateField =>
                    'The shop is closed on the selected day.',
            ]);
        }

        $opensAt =
            CarbonImmutable::parse(
                $startsAt
                    ->toDateString()
                .' '
                .$schedule->opens_at,
                $timezone
            );

        if (
            $startsAt->lessThan(
                $opensAt
            )
        ) {
            throw ValidationException::withMessages([
                $timeField =>
                    'Please select one of the available appointment times.',
            ]);
        }

        $minutesFromOpening =
            intdiv(
                $startsAt->getTimestamp()
                -
                $opensAt->getTimestamp(),
                60
            );

        if (
            $minutesFromOpening < 0
            ||
            $minutesFromOpening
                %
                $rules
                    ->slot_interval_minutes
                !== 0
        ) {
            throw ValidationException::withMessages([
                $timeField =>
                    'Please select one of the available appointment times.',
            ]);
        }
    }

    /**
     * Protect customer cancellation according to the shop rule.
     *
     * Shop suspension intentionally does NOT prevent cancellation of an
     * existing appointment.
     */
    public function assertCustomerCancellationAllowed(
        Appointment $appointment
    ): void {
        $shop =
            $this->shopForAppointment(
                $appointment
            );

        $rules =
            $this->resolver->forShop(
                $shop
            );

        $cutoff =
            $rules
                ->cancellation_cutoff_minutes;

        /*
         * Zero preserves StyleHub's original behavior:
         * customer may cancel until appointment start.
         */

        if (
            $cutoff <= 0
        ) {
            return;
        }

        $this->assertBeforeCutoff(
            appointment:
                $appointment,

            cutoffMinutes:
                $cutoff,

            message:
                sprintf(
                    'This appointment can only be cancelled at least %d minutes before its scheduled start time.',
                    $cutoff
                ),
        );
    }

    /**
     * Protect creation of customer reschedule requests.
     */
    public function assertCustomerRescheduleAllowed(
        Appointment $appointment
    ): void {
        $shop =
            $this->shopForAppointment(
                $appointment
            );

        $rules =
            $this->resolver->forShop(
                $shop
            );

        $cutoff =
            $rules
                ->reschedule_cutoff_minutes;

        if (
            $cutoff <= 0
        ) {
            return;
        }

        $this->assertBeforeCutoff(
            appointment:
                $appointment,

            cutoffMinutes:
                $cutoff,

            message:
                sprintf(
                    'This appointment can only be rescheduled at least %d minutes before its scheduled start time.',
                    $cutoff
                ),
        );
    }

    private function assertBeforeCutoff(
        Appointment $appointment,
        int $cutoffMinutes,
        string $message
    ): void {
        $startsAt =
            $this->appointmentStart(
                $appointment
            );

        $deadline =
            $startsAt->subMinutes(
                $cutoffMinutes
            );

        $now =
            CarbonImmutable::now(
                $this->timezone()
            );

        /*
         * Exact boundary is allowed.
         *
         * Appointment: 10:00
         * Cutoff:      120 min
         * Deadline:    08:00
         *
         * 08:00:00 = allowed
         * 08:00:01 = too late
         */

        if (
            $now->greaterThan(
                $deadline
            )
        ) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                $message
            );
        }
    }

    private function shopForAppointment(
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
            $this->timezone()
        );
    }

    private function timezone(): string
    {
        return (string) config(
            'app.timezone',
            'Asia/Manila'
        );
    }
}