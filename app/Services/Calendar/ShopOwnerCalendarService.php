<?php

namespace App\Services\Calendar;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Shop;
use App\Models\ShopBlockedDate;
use App\Models\ShopBreakPeriod;
use App\Models\ShopSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ShopOwnerCalendarService
{
    /**
     * Build one complete month of calendar summary data.
     *
     * @return array<string, mixed>
     */
    public function month(
        Shop $shop,
        CarbonImmutable $month
    ): array {
        $firstDate =
            $month->startOfMonth();

        $lastDate =
            $month->endOfMonth();

        /*
        |--------------------------------------------------------------------------
        | Weekly Schedule
        |--------------------------------------------------------------------------
        */

        $schedules =
            $shop
                ->schedules()
                ->get()
                ->keyBy(
                    static fn (
                        ShopSchedule $schedule
                    ): int =>
                        $schedule
                            ->day_of_week
                            ->value
                );

        /*
        |--------------------------------------------------------------------------
        | Blocked Dates
        |--------------------------------------------------------------------------
        */

        $blockedDates =
            $shop
                ->blockedDates()
                ->whereBetween(
                    'blocked_date',
                    [
                        $firstDate
                            ->toDateString(),

                        $lastDate
                            ->toDateString(),
                    ]
                )
                ->get()
                ->keyBy(
                    static fn (
                        ShopBlockedDate $blockedDate
                    ): string =>
                        $blockedDate
                            ->blocked_date
                            ->format(
                                'Y-m-d'
                            )
                );

        /*
        |--------------------------------------------------------------------------
        | Weekly Break Periods
        |--------------------------------------------------------------------------
        */

        $breakPeriodsByWeekday =
            $shop
                ->breakPeriods()
                ->ordered()
                ->get()
                ->groupBy(
                    static fn (
                        ShopBreakPeriod $breakPeriod
                    ): int =>
                        $breakPeriod
                            ->day_of_week
                            ->value
                );

        /*
        |--------------------------------------------------------------------------
        | Appointments
        |--------------------------------------------------------------------------
        */

        $appointmentsByDate =
            $shop
                ->appointments()
                ->whereBetween(
                    'appointment_date',
                    [
                        $firstDate
                            ->toDateString(),

                        $lastDate
                            ->toDateString(),
                    ]
                )
                ->orderBy(
                    'appointment_date'
                )
                ->orderBy(
                    'starts_at'
                )
                ->get()
                ->groupBy(
                    static fn (
                        Appointment $appointment
                    ): string =>
                        $appointment
                            ->appointment_date
                            ->toDateString()
                );

        $today =
            CarbonImmutable::now(
                $this->timezone()
            )->startOfDay();

        $days = [];

        /*
        |--------------------------------------------------------------------------
        | Build Every Calendar Day
        |--------------------------------------------------------------------------
        */

        for (
            $date = $firstDate;
            $date->lessThanOrEqualTo(
                $lastDate
            );
            $date = $date->addDay()
        ) {
            $dateKey =
                $date->toDateString();

            /** @var Collection<int, Appointment> $dayAppointments */
            $dayAppointments =
                $appointmentsByDate->get(
                    $dateKey,
                    collect()
                );

            $statusCounts =
                $this->statusCounts(
                    $dayAppointments
                );

            /** @var ShopSchedule|null $schedule */
            $schedule =
                $schedules->get(
                    $date->isoWeekday()
                );

            /** @var ShopBlockedDate|null $blockedDate */
            $blockedDate =
                $blockedDates->get(
                    $dateKey
                );

            $breakPeriodCount =
                $breakPeriodsByWeekday
                    ->get(
                        $date->isoWeekday(),
                        collect()
                    )
                    ->count();

            $days[] = [
                'date' =>
                    $dateKey,

                'day_of_week' =>
                    $date->isoWeekday(),

                'day_label' =>
                    $date->format(
                        'l'
                    ),

                'short_day_label' =>
                    $date->format(
                        'D'
                    ),

                'day_number' =>
                    $date->day,

                'is_today' =>
                    $date->equalTo(
                        $today
                    ),

                'schedule' =>
                    $this->scheduleData(
                        $schedule
                    ),

                'is_blocked' =>
                    $blockedDate !== null,

                'blocked_reason' =>
                    $blockedDate?->reason,

                'break_period_count' =>
                    $breakPeriodCount,

                'has_break_periods' =>
                    $breakPeriodCount > 0,

                'appointment_count' =>
                    $dayAppointments
                        ->count(),

                'active_appointment_count' =>
                    $statusCounts[
                        'pending'
                    ]
                    +
                    $statusCounts[
                        'confirmed'
                    ],

                'status_counts' =>
                    $statusCounts,
            ];
        }

        return [
            'month' =>
                $firstDate->format(
                    'Y-m'
                ),

            'month_label' =>
                $firstDate->format(
                    'F Y'
                ),

            'timezone' =>
                $this->timezone(),

            'days' =>
                $days,
        ];
    }

    /**
     * Build the operational agenda for one selected date.
     *
     * @return array<string, mixed>
     */
    public function agenda(
        Shop $shop,
        CarbonImmutable $date
    ): array {
        $date =
            $date->startOfDay();

        $dateKey =
            $date->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Schedule
        |--------------------------------------------------------------------------
        */

        $schedule =
            $shop
                ->schedules()
                ->where(
                    'day_of_week',
                    $date->isoWeekday()
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Blocked Date
        |--------------------------------------------------------------------------
        */

        $blockedDate =
            $shop
                ->blockedDates()
                ->whereDate(
                    'blocked_date',
                    $dateKey
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Break Periods
        |--------------------------------------------------------------------------
        */

        $breakPeriods =
            $shop
                ->breakPeriods()
                ->where(
                    'day_of_week',
                    $date->isoWeekday()
                )
                ->ordered()
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Day Appointments
        |--------------------------------------------------------------------------
        */

        /** @var EloquentCollection<int, Appointment> $appointments */
        $appointments =
            $shop
                ->appointments()
                ->with([
                    'customer',
                    'service',
                ])
                ->whereDate(
                    'appointment_date',
                    $dateKey
                )
                ->orderBy(
                    'starts_at'
                )
                ->orderBy(
                    'id'
                )
                ->get();

        $statusCounts =
            $this->statusCounts(
                $appointments
            );

        return [
            'date' => [
                'value' =>
                    $dateKey,

                'day_of_week' =>
                    $date->isoWeekday(),

                'day_label' =>
                    $date->format(
                        'l'
                    ),

                'long_label' =>
                    $date->format(
                        'F j, Y'
                    ),

                'is_today' =>
                    $date->isSameDay(
                        CarbonImmutable::now(
                            $this->timezone()
                        )
                    ),
            ],

            'timezone' =>
                $this->timezone(),

            'schedule' =>
                $this->scheduleData(
                    $schedule
                ),

            /*
            |--------------------------------------------------------------------------
            | Shop Owner-Only Closure Context
            |--------------------------------------------------------------------------
            */

            'blocked_date' =>
                $blockedDate === null
                    ? null
                    : [
                        'id' =>
                            $blockedDate->id,

                        'reason' =>
                            $blockedDate->reason,
                    ],

            /*
            |--------------------------------------------------------------------------
            | Shop Owner-Only Break Context
            |--------------------------------------------------------------------------
            */

            'break_periods' =>
                $breakPeriods
                    ->map(
                        fn (
                            ShopBreakPeriod $breakPeriod
                        ): array => [
                            'id' =>
                                $breakPeriod->id,

                            'starts_at' =>
                                $this->displayTime(
                                    $breakPeriod
                                        ->starts_at
                                ),

                            'ends_at' =>
                                $this->displayTime(
                                    $breakPeriod
                                        ->ends_at
                                ),

                            'label' =>
                                $breakPeriod->label,
                        ]
                    )
                    ->values()
                    ->all(),

            /*
            |--------------------------------------------------------------------------
            | Day Summary
            |--------------------------------------------------------------------------
            */

            'summary' => [
                'appointment_count' =>
                    $appointments->count(),

                'active_appointment_count' =>
                    $statusCounts[
                        'pending'
                    ]
                    +
                    $statusCounts[
                        'confirmed'
                    ],

                'status_counts' =>
                    $statusCounts,
            ],

            /*
             * The controller transforms these through the existing secure
             * ShopOwnerAppointmentResource before returning the response.
             */

            'appointments' =>
                $appointments,
        ];
    }

    /**
     * @param Collection<int, Appointment> $appointments
     *
     * @return array{
     *     pending: int,
     *     confirmed: int,
     *     completed: int,
     *     cancelled: int,
     *     declined: int
     * }
     */
    private function statusCounts(
        Collection $appointments
    ): array {
        $counts = [
            AppointmentStatus::Pending
                ->value => 0,

            AppointmentStatus::Confirmed
                ->value => 0,

            AppointmentStatus::Completed
                ->value => 0,

            AppointmentStatus::Cancelled
                ->value => 0,

            AppointmentStatus::Declined
                ->value => 0,
        ];

        foreach (
            $appointments as $appointment
        ) {
            $status =
                $appointment
                    ->status
                    ->value;

            if (
                array_key_exists(
                    $status,
                    $counts
                )
            ) {
                $counts[
                    $status
                ]++;
            }
        }

        return $counts;
    }

    /**
     * @return array{
     *     is_open: bool,
     *     opens_at: string|null,
     *     closes_at: string|null
     * }
     */
    private function scheduleData(
        ?ShopSchedule $schedule
    ): array {
        if (
            $schedule === null
            ||
            ! $schedule->isOpen()
            ||
            ! $schedule->hasOperatingHours()
        ) {
            return [
                'is_open' =>
                    false,

                'opens_at' =>
                    null,

                'closes_at' =>
                    null,
            ];
        }

        return [
            'is_open' =>
                true,

            'opens_at' =>
                $this->displayTime(
                    $schedule->opens_at
                ),

            'closes_at' =>
                $this->displayTime(
                    $schedule->closes_at
                ),
        ];
    }

    private function displayTime(
        ?string $time
    ): ?string {
        if (
            $time === null
        ) {
            return null;
        }

        return substr(
            $time,
            0,
            5
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