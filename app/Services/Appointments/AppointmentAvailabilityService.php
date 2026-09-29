<?php

namespace App\Services\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopBreakPeriod;
use App\Models\ShopSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AppointmentAvailabilityService
{
    /**
     * Return upcoming dates and determine whether each date
     * currently contains at least one valid booking slot.
     *
     * @return array<string, mixed>
     */
    public function availableDates(
        User $customer,
        int $shopId,
        int $serviceId,
        int $days
    ): array {
        [
            $shop,
            $service,
        ] = $this->resolveBookableContext(
            $shopId,
            $serviceId
        );

        $timezone = $this->timezone();

        $now = CarbonImmutable::now(
            $timezone
        );

        $firstDate = $now->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | Restrict Requested Preview
        |--------------------------------------------------------------------------
        */

        $requestedDays = max(
            1,
            min(
                $days,
                $this->maximumPreviewDays(),
                $this->bookingWindowDays() + 1
            )
        );

        $lastDate = $firstDate->addDays(
            $requestedDays - 1
        );

        /*
        |--------------------------------------------------------------------------
        | Load Weekly Shop Schedules Once
        |--------------------------------------------------------------------------
        */

        $schedules = $shop
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
        | Load Weekly Break Periods Once
        |--------------------------------------------------------------------------
        */

        $breakPeriodsByDay = $shop
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
        | Load Shop Appointments
        |--------------------------------------------------------------------------
        */

        $shopAppointmentsByDate =
            $this->blockingAppointmentsByDate(
                shopId: $shop->id,
                customerId: null,
                fromDate: $firstDate,
                toDate: $lastDate,
            );

        /*
        |--------------------------------------------------------------------------
        | Load Customer Appointments
        |--------------------------------------------------------------------------
        */

        $customerAppointmentsByDate =
            $this->blockingAppointmentsByDate(
                shopId: null,
                customerId: $customer->id,
                fromDate: $firstDate,
                toDate: $lastDate,
            );

        $dates = [];

        /*
        |--------------------------------------------------------------------------
        | Generate Calendar Availability
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

            $dayAvailability =
                $this->availabilityForDate(
                    shop: $shop,
                    service: $service,
                    date: $date,
                    now: $now,
                    schedule: $schedules->get(
                        $date->isoWeekday()
                    ),
                    breakPeriods:
                        $breakPeriodsByDay->get(
                            $date->isoWeekday(),
                            collect()
                        ),
                    shopAppointments:
                        $shopAppointmentsByDate->get(
                            $dateKey,
                            collect()
                        ),
                    customerAppointments:
                        $customerAppointmentsByDate->get(
                            $dateKey,
                            collect()
                        ),
                );

            $dates[] = [
                'date' =>
                    $dayAvailability['date'],

                'day_label' =>
                    $dayAvailability['day_label'],

                'short_day_label' =>
                    $date->format('D'),

                'is_open' =>
                    $dayAvailability['is_open'],

                'is_available' =>
                    $dayAvailability['slots'] !== [],

                'available_slot_count' =>
                    count(
                        $dayAvailability['slots']
                    ),

                'opens_at' =>
                    $dayAvailability['opens_at'],

                'closes_at' =>
                    $dayAvailability['closes_at'],

                'unavailable_reason' =>
                    $dayAvailability['unavailable_reason'],
            ];
        }

        return [
            'shop' => [
                'id' =>
                    $shop->id,

                'name' =>
                    $shop->name,
            ],

            'service' => [
                'id' =>
                    $service->id,

                'name' =>
                    $service->name,

                'duration_minutes' =>
                    (int) $service->duration_minutes,
            ],

            'timezone' =>
                $timezone,

            'slot_interval_minutes' =>
                $this->slotIntervalMinutes(),

            'booking_window' => [
                'from' =>
                    $firstDate->toDateString(),

                'to' =>
                    $firstDate
                        ->addDays(
                            $this->bookingWindowDays()
                        )
                        ->toDateString(),
            ],

            'dates' =>
                $dates,
        ];
    }

    /**
     * Return all currently valid appointment slots
     * for one specific date.
     *
     * @return array<string, mixed>
     */
    public function availableSlots(
        User $customer,
        int $shopId,
        int $serviceId,
        string $date
    ): array {
        [
            $shop,
            $service,
        ] = $this->resolveBookableContext(
            $shopId,
            $serviceId
        );

        $timezone =
            $this->timezone();

        $now =
            CarbonImmutable::now(
                $timezone
            );

        $selectedDate =
            CarbonImmutable::parse(
                $date,
                $timezone
            )
                ->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | Booking Window Security
        |--------------------------------------------------------------------------
        */

        $this->assertDateWithinBookingWindow(
            selectedDate:
                $selectedDate,

            now:
                $now,

            field:
                'date',
        );

        /*
        |--------------------------------------------------------------------------
        | Shop Schedule
        |--------------------------------------------------------------------------
        */

        $schedule =
            $shop
                ->schedules()
                ->where(
                    'day_of_week',
                    $selectedDate->isoWeekday()
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Recurring Break Periods
        |--------------------------------------------------------------------------
        */

        $breakPeriods =
            $shop
                ->breakPeriods()
                ->where(
                    'day_of_week',
                    $selectedDate->isoWeekday()
                )
                ->ordered()
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Existing Shop Bookings
        |--------------------------------------------------------------------------
        */

        $shopAppointments =
            $this->blockingAppointmentsForDate(
                shopId:
                    $shop->id,

                customerId:
                    null,

                date:
                    $selectedDate,
            );

        /*
        |--------------------------------------------------------------------------
        | Existing Customer Bookings
        |--------------------------------------------------------------------------
        */

        $customerAppointments =
            $this->blockingAppointmentsForDate(
                shopId:
                    null,

                customerId:
                    $customer->id,

                date:
                    $selectedDate,
            );

        $dayAvailability =
            $this->availabilityForDate(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    $selectedDate,

                now:
                    $now,

                schedule:
                    $schedule,

                breakPeriods:
                    $breakPeriods,

                shopAppointments:
                    $shopAppointments,

                customerAppointments:
                    $customerAppointments,
            );

        return [
            'shop' => [
                'id' =>
                    $shop->id,

                'name' =>
                    $shop->name,
            ],

            'service' => [
                'id' =>
                    $service->id,

                'name' =>
                    $service->name,

                'duration_minutes' =>
                    (int) $service->duration_minutes,
            ],

            'timezone' =>
                $timezone,

            'slot_interval_minutes' =>
                $this->slotIntervalMinutes(),

            'date' => [
                'value' =>
                    $dayAvailability['date'],

                'day_label' =>
                    $dayAvailability['day_label'],

                'is_open' =>
                    $dayAvailability['is_open'],

                'opens_at' =>
                    $dayAvailability['opens_at'],

                'closes_at' =>
                    $dayAvailability['closes_at'],

                'unavailable_reason' =>
                    $dayAvailability['unavailable_reason'],
            ],

            'slots' =>
                $dayAvailability['slots'],
        ];
    }

    /**
     * Validate that the selected date is inside the allowed
     * appointment booking window.
     */
    public function assertDateWithinBookingWindow(
        CarbonImmutable $selectedDate,
        ?CarbonImmutable $now = null,
        string $field = 'appointment_date'
    ): void {
        $now ??=
            CarbonImmutable::now(
                $this->timezone()
            );

        $today =
            $now->startOfDay();

        $selectedDate =
            $selectedDate->startOfDay();

        if (
            $selectedDate->lessThan(
                $today
            )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'The appointment date cannot be in the past.',
            ]);
        }

        $latestDate =
            $today->addDays(
                $this->bookingWindowDays()
            );

        if (
            $selectedDate->greaterThan(
                $latestDate
            )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'The appointment date is outside the allowed booking window.',
            ]);
        }
    }

    /**
     * Prevent a modified client from using a start time
     * outside the server-approved slot grid.
     */
    public function assertStartTimeMatchesSlotGrid(
        CarbonImmutable $startsAt,
        CarbonImmutable $opensAt
    ): void {
        if (
            $startsAt->lessThan(
                $opensAt
            )
        ) {
            throw ValidationException::withMessages([
                'starts_at' =>
                    'Please select one of the available appointment times.',
            ]);
        }

        $minutesFromOpening =
            intdiv(
                $startsAt->getTimestamp()
                - $opensAt->getTimestamp(),
                60
            );

        if (
            $minutesFromOpening < 0
            ||
            $minutesFromOpening
                % $this->slotIntervalMinutes()
                !== 0
        ) {
            throw ValidationException::withMessages([
                'starts_at' =>
                    'Please select one of the available appointment times.',
            ]);
        }
    }

    /**
     * Enforce minimum booking lead time.
     */
    public function assertMinimumLeadTime(
        CarbonImmutable $startsAt,
        ?CarbonImmutable $now = null
    ): void {
        $now ??=
            CarbonImmutable::now(
                $this->timezone()
            );

        $minimumLeadTime =
            $this->minimumLeadTimeMinutes();

        if (
            $minimumLeadTime <= 0
        ) {
            return;
        }

        if (
            $startsAt->lessThan(
                $now->addMinutes(
                    $minimumLeadTime
                )
            )
        ) {
            throw ValidationException::withMessages([
                'starts_at' =>
                    sprintf(
                        'Appointments must be booked at least %d minutes in advance.',
                        $minimumLeadTime
                    ),
            ]);
        }
    }

    /**
     * Appointment start-time interval.
     */
    public function slotIntervalMinutes(): int
    {
        return max(
            5,
            min(
                60,
                (int) config(
                    'appointments.slot_interval_minutes',
                    15
                )
            )
        );
    }

    /**
     * Minimum advance notice.
     */
    public function minimumLeadTimeMinutes(): int
    {
        return max(
            0,
            min(
                1440,
                (int) config(
                    'appointments.minimum_lead_time_minutes',
                    0
                )
            )
        );
    }

    /**
     * Maximum number of days into the future that may be booked.
     */
    public function bookingWindowDays(): int
    {
        return max(
            1,
            min(
                365,
                (int) config(
                    'appointments.booking_window_days',
                    60
                )
            )
        );
    }

    /**
     * Default number of calendar dates shown in the app.
     */
    public function defaultPreviewDays(): int
    {
        return max(
            1,
            min(
                $this->maximumPreviewDays(),
                (int) config(
                    'appointments.availability_preview_days',
                    30
                )
            )
        );
    }

    /**
     * Application timezone used for booking calculations.
     */
    public function timezone(): string
    {
        return (string) config(
            'app.timezone',
            'Asia/Manila'
        );
    }

    /**
     * Protect API/database performance by restricting very large
     * availability requests.
     */
    private function maximumPreviewDays(): int
    {
        return 60;
    }

    /**
     * Resolve a customer-visible shop and active service.
     *
     * @return array{0: Shop, 1: Service}
     */
    private function resolveBookableContext(
        int $shopId,
        int $serviceId
    ): array {
        $shop =
            Shop::query()
                ->visibleToCustomers()
                ->whereKey(
                    $shopId
                )
                ->firstOrFail();

        $service =
            $shop
                ->services()
                ->whereKey(
                    $serviceId
                )
                ->where(
                    'is_active',
                    true
                )
                ->firstOrFail();

        return [
            $shop,
            $service,
        ];
    }

    /**
     * Calculate availability for one specific date.
     *
     * @return array<string, mixed>
     */
    private function availabilityForDate(
        Shop $shop,
        Service $service,
        CarbonImmutable $date,
        CarbonImmutable $now,
        ?ShopSchedule $schedule,
        Collection $breakPeriods,
        Collection $shopAppointments,
        Collection $customerAppointments
    ): array {
        $base = [
            'date' =>
                $date->toDateString(),

            'day_label' =>
                $date->format('l'),

            'is_open' =>
                false,

            'opens_at' =>
                null,

            'closes_at' =>
                null,

            'unavailable_reason' =>
                null,

            'slots' =>
                [],
        ];

        /*
        |--------------------------------------------------------------------------
        | Shop Not Accepting Appointments
        |--------------------------------------------------------------------------
        */

        if (
            ! $shop
                ->availability_status
                ->acceptsAppointments()
        ) {
            return [
                ...$base,

                'unavailable_reason' =>
                    'shop_not_accepting_appointments',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Closed Day
        |--------------------------------------------------------------------------
        */

        if (
            $schedule === null
            ||
            ! $schedule->isOpen()
            ||
            ! $schedule->hasOperatingHours()
        ) {
            return [
                ...$base,

                'unavailable_reason' =>
                    'shop_closed',
            ];
        }

        $timezone =
            $this->timezone();

        $opensAt =
            CarbonImmutable::parse(
                $date->toDateString()
                .' '
                .$schedule->opens_at,
                $timezone
            );

        $closesAt =
            CarbonImmutable::parse(
                $date->toDateString()
                .' '
                .$schedule->closes_at,
                $timezone
            );

        /*
        |--------------------------------------------------------------------------
        | Generate Actual Valid Slots
        |--------------------------------------------------------------------------
        */

        $slots =
            $this->generateSlots(
                service:
                    $service,

                opensAt:
                    $opensAt,

                closesAt:
                    $closesAt,

                now:
                    $now,

                breakPeriods:
                    $breakPeriods,

                shopAppointments:
                    $shopAppointments,

                customerAppointments:
                    $customerAppointments,
            );

        return [
            ...$base,

            'is_open' =>
                true,

            'opens_at' =>
                $opensAt->format(
                    'H:i'
                ),

            'closes_at' =>
                $closesAt->format(
                    'H:i'
                ),

            'unavailable_reason' =>
                $slots === []
                    ? 'no_available_slots'
                    : null,

            'slots' =>
                $slots,
        ];
    }

    /**
     * Generate all valid appointment slots.
     *
     * @return list<array{
     *     starts_at: string,
     *     ends_at: string,
     *     label: string
     * }>
     */
    private function generateSlots(
        Service $service,
        CarbonImmutable $opensAt,
        CarbonImmutable $closesAt,
        CarbonImmutable $now,
        Collection $breakPeriods,
        Collection $shopAppointments,
        Collection $customerAppointments
    ): array {
        $durationMinutes =
            (int) $service->duration_minutes;

        $intervalMinutes =
            $this->slotIntervalMinutes();

        $minimumStart =
            $now->addMinutes(
                $this->minimumLeadTimeMinutes()
            );

        $slots = [];

        for (
            $candidateStart = $opensAt;
            $candidateStart
                ->addMinutes(
                    $durationMinutes
                )
                ->lessThanOrEqualTo(
                    $closesAt
                );
            $candidateStart =
                $candidateStart->addMinutes(
                    $intervalMinutes
                )
        ) {
            $candidateEnd =
                $candidateStart->addMinutes(
                    $durationMinutes
                );

            /*
            |--------------------------------------------------------------------------
            | Must Be in the Future
            |--------------------------------------------------------------------------
            */

            if (
                ! $candidateStart->isAfter(
                    $now
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Minimum Advance Notice
            |--------------------------------------------------------------------------
            */

            if (
                $candidateStart->lessThan(
                    $minimumStart
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Recurring Shop Break
            |--------------------------------------------------------------------------
            |
            | The whole service interval must fit outside all Shop Owner breaks.
            |
            | Example:
            |
            | Break:       10:00 - 11:00
            | Appointment: 09:30 - 10:30
            |
            | This must be rejected because it overlaps the break even though
            | the appointment begins before the break starts.
            |
            */

            if (
                $this->overlapsAnyTimeRange(
                    $breakPeriods,
                    $candidateStart,
                    $candidateEnd
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Shop Appointment Conflict
            |--------------------------------------------------------------------------
            */

            if (
                $this->overlapsAnyTimeRange(
                    $shopAppointments,
                    $candidateStart,
                    $candidateEnd
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Customer Appointment Conflict
            |--------------------------------------------------------------------------
            */

            if (
                $this->overlapsAnyTimeRange(
                    $customerAppointments,
                    $candidateStart,
                    $candidateEnd
                )
            ) {
                continue;
            }

            $slots[] = [
                'starts_at' =>
                    $candidateStart->format(
                        'H:i'
                    ),

                'ends_at' =>
                    $candidateEnd->format(
                        'H:i'
                    ),

                'label' =>
                    sprintf(
                        '%s - %s',
                        $candidateStart->format(
                            'g:i A'
                        ),
                        $candidateEnd->format(
                            'g:i A'
                        )
                    ),
            ];
        }

        return $slots;
    }

    /**
     * Retrieve blocking appointments for one date.
     *
     * @return EloquentCollection<int, Appointment>
     */
    private function blockingAppointmentsForDate(
        ?int $shopId,
        ?int $customerId,
        CarbonImmutable $date
    ): EloquentCollection {
        $query =
            Appointment::query()
                ->select([
                    'id',
                    'appointment_date',
                    'starts_at',
                    'ends_at',
                ])
                ->whereDate(
                    'appointment_date',
                    $date->toDateString()
                )
                ->whereIn(
                    'status',
                    $this->blockingStatusValues()
                );

        if (
            $shopId !== null
        ) {
            $query->where(
                'shop_id',
                $shopId
            );
        }

        if (
            $customerId !== null
        ) {
            $query->where(
                'customer_id',
                $customerId
            );
        }

        return $query->get();
    }

    /**
     * Retrieve blocking appointments across a date range
     * and group them by appointment date.
     *
     * @return Collection<string, Collection<int, Appointment>>
     */
    private function blockingAppointmentsByDate(
        ?int $shopId,
        ?int $customerId,
        CarbonImmutable $fromDate,
        CarbonImmutable $toDate
    ): Collection {
        $query =
            Appointment::query()
                ->select([
                    'id',
                    'appointment_date',
                    'starts_at',
                    'ends_at',
                ])
                ->whereBetween(
                    'appointment_date',
                    [
                        $fromDate->toDateString(),
                        $toDate->toDateString(),
                    ]
                )
                ->whereIn(
                    'status',
                    $this->blockingStatusValues()
                );

        if (
            $shopId !== null
        ) {
            $query->where(
                'shop_id',
                $shopId
            );
        }

        if (
            $customerId !== null
        ) {
            $query->where(
                'customer_id',
                $customerId
            );
        }

        return $query
            ->get()
            ->groupBy(
                static fn (
                    Appointment $appointment
                ): string =>
                    $appointment
                        ->appointment_date
                        ->toDateString()
            );
    }

    /**
     * Appointment statuses that occupy time.
     *
     * @return list<string>
     */
    private function blockingStatusValues(): array
    {
        return array_values(
            array_map(
                static fn (
                    AppointmentStatus $status
                ): string =>
                    $status->value,

                array_filter(
                    AppointmentStatus::cases(),

                    static fn (
                        AppointmentStatus $status
                    ): bool =>
                        $status->blocksTimeSlot()
                )
            )
        );
    }

    /**
     * Determine whether a proposed appointment interval overlaps
     * any appointment or recurring break interval.
     */
    private function overlapsAnyTimeRange(
        Collection $timeRanges,
        CarbonImmutable $candidateStart,
        CarbonImmutable $candidateEnd
    ): bool {
        $candidateStartTime =
            $candidateStart->format(
                'H:i:s'
            );

        $candidateEndTime =
            $candidateEnd->format(
                'H:i:s'
            );

        foreach (
            $timeRanges as $timeRange
        ) {
            if (
                $timeRange->starts_at
                    < $candidateEndTime
                &&
                $timeRange->ends_at
                    > $candidateStartTime
            ) {
                return true;
            }
        }

        return false;
    }
}