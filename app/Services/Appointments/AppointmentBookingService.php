<?php

namespace App\Services\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    public function __construct(
        private readonly AppointmentAvailabilityService $availabilityService
    ) {}

    /**
     * Securely create a customer appointment.
     *
     * @param array<string, mixed> $data
     */
    public function book(
        User $customer,
        array $data
    ): Appointment {
        return DB::transaction(
            function () use (
                $customer,
                $data
            ): Appointment {

                /*
                |--------------------------------------------------------------------------
                | Lock Customer
                |--------------------------------------------------------------------------
                |
                | Every booking created by this customer acquires the same user
                | row lock.
                |
                | This prevents two concurrent requests from allowing the same
                | customer to book overlapping appointments at different shops.
                |
                */

                $lockedCustomer =
                    User::query()
                        ->whereKey(
                            $customer->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | Lock and Validate Shop
                |--------------------------------------------------------------------------
                |
                | visibleToCustomers() additionally protects against:
                |
                | - pending shops
                | - rejected shops
                | - suspended shop owners
                |
                */

                $shop =
                    Shop::query()
                        ->visibleToCustomers()
                        ->whereKey(
                            $data['shop_id']
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $shop === null
                ) {
                    throw ValidationException::withMessages([
                        'shop_id' =>
                            'The selected shop is not available for booking.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Shop Availability
                |--------------------------------------------------------------------------
                */

                if (
                    ! $shop
                        ->availability_status
                        ->acceptsAppointments()
                ) {
                    throw ValidationException::withMessages([
                        'shop_id' =>
                            'The selected shop is not currently accepting appointments.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Lock and Validate Service
                |--------------------------------------------------------------------------
                |
                | The service must actually belong to the selected shop.
                |
                | This protects against manipulating service_id.
                |
                */

                $service =
                    Service::query()
                        ->whereKey(
                            $data['service_id']
                        )
                        ->where(
                            'shop_id',
                            $shop->id
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $service === null
                    ||
                    ! $service->isActive()
                ) {
                    throw ValidationException::withMessages([
                        'service_id' =>
                            'The selected service is not available for booking.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Build Appointment Date/Time
                |--------------------------------------------------------------------------
                */

                $timezone =
                    $this
                        ->availabilityService
                        ->timezone();

                $startsAt =
                    CarbonImmutable::parse(
                        $data['appointment_date']
                        .' '
                        .$data['starts_at']
                        .':00',
                        $timezone
                    );

                $now =
                    CarbonImmutable::now(
                        $timezone
                    );

                /*
                |--------------------------------------------------------------------------
                | Prevent Past or Current-Time Appointments
                |--------------------------------------------------------------------------
                */

                if (
                    ! $startsAt->isAfter(
                        $now
                    )
                ) {
                    throw ValidationException::withMessages([
                        'starts_at' =>
                            'The appointment must be scheduled for a future date and time.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Enforce Booking Window
                |--------------------------------------------------------------------------
                */

                $this
                    ->availabilityService
                    ->assertDateWithinBookingWindow(
                        selectedDate:
                            $startsAt->startOfDay(),

                        now:
                            $now,

                        field:
                            'appointment_date',
                    );

                /*
                |--------------------------------------------------------------------------
                | Enforce Minimum Lead Time
                |--------------------------------------------------------------------------
                */

                $this
                    ->availabilityService
                    ->assertMinimumLeadTime(
                        startsAt:
                            $startsAt,

                        now:
                            $now,
                    );

                /*
                |--------------------------------------------------------------------------
                | Calculate Server-Side End Time
                |--------------------------------------------------------------------------
                |
                | The client does NOT decide the end time.
                |
                */

                $endsAt =
                    $startsAt->addMinutes(
                        $service->duration_minutes
                    );

                if (
                    $endsAt->toDateString()
                    !==
                    $startsAt->toDateString()
                ) {
                    throw ValidationException::withMessages([
                        'starts_at' =>
                            'The selected service would extend beyond the shop operating day.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Retrieve Shop Schedule
                |--------------------------------------------------------------------------
                */

                $schedule =
                    $shop
                        ->schedules()
                        ->where(
                            'day_of_week',
                            $startsAt->isoWeekday()
                        )
                        ->first();

                if (
                    $schedule === null
                    ||
                    ! $schedule->isOpen()
                    ||
                    ! $schedule->hasOperatingHours()
                ) {
                    throw ValidationException::withMessages([
                        'appointment_date' =>
                            'The selected shop is closed on this day.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Operating Hours
                |--------------------------------------------------------------------------
                */

                $opensAt =
                    CarbonImmutable::parse(
                        $startsAt->toDateString()
                        .' '
                        .$schedule->opens_at,
                        $timezone
                    );

                $closesAt =
                    CarbonImmutable::parse(
                        $startsAt->toDateString()
                        .' '
                        .$schedule->closes_at,
                        $timezone
                    );

                /*
                |--------------------------------------------------------------------------
                | Ensure Entire Appointment Fits
                |--------------------------------------------------------------------------
                */

                if (
                    $startsAt->lessThan(
                        $opensAt
                    )
                    ||
                    $endsAt->greaterThan(
                        $closesAt
                    )
                ) {
                    throw ValidationException::withMessages([
                        'starts_at' =>
                            'The appointment must fit completely within the shop operating hours.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent Forged Time Slots
                |--------------------------------------------------------------------------
                |
                | If the server generates:
                |
                | 09:00
                | 09:15
                | 09:30
                |
                | a modified client cannot manually submit:
                |
                | 09:07
                |
                */

                $this
                    ->availabilityService
                    ->assertStartTimeMatchesSlotGrid(
                        startsAt:
                            $startsAt,

                        opensAt:
                            $opensAt,
                    );

                /*
                |--------------------------------------------------------------------------
                | Appointment Statuses That Occupy Time
                |--------------------------------------------------------------------------
                */

                $blockingStatuses = [
                    AppointmentStatus::Pending
                        ->value,

                    AppointmentStatus::Confirmed
                        ->value,
                ];

                /*
                |--------------------------------------------------------------------------
                | Shop Conflict Check
                |--------------------------------------------------------------------------
                |
                | Overlap rule:
                |
                | existing_start < requested_end
                |
                | AND
                |
                | existing_end > requested_start
                |
                */

                $shopConflict =
                    Appointment::query()
                        ->where(
                            'shop_id',
                            $shop->id
                        )
                        ->whereDate(
                            'appointment_date',
                            $startsAt->toDateString()
                        )
                        ->whereIn(
                            'status',
                            $blockingStatuses
                        )
                        ->where(
                            'starts_at',
                            '<',
                            $endsAt->format(
                                'H:i:s'
                            )
                        )
                        ->where(
                            'ends_at',
                            '>',
                            $startsAt->format(
                                'H:i:s'
                            )
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $shopConflict !== null
                ) {
                    throw ValidationException::withMessages([
                        'starts_at' =>
                            'The selected appointment time is no longer available.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Customer Conflict Check
                |--------------------------------------------------------------------------
                |
                | A customer cannot book two overlapping appointments even when
                | those appointments belong to different shops.
                |
                */

                $customerConflict =
                    Appointment::query()
                        ->where(
                            'customer_id',
                            $lockedCustomer->id
                        )
                        ->whereDate(
                            'appointment_date',
                            $startsAt->toDateString()
                        )
                        ->whereIn(
                            'status',
                            $blockingStatuses
                        )
                        ->where(
                            'starts_at',
                            '<',
                            $endsAt->format(
                                'H:i:s'
                            )
                        )
                        ->where(
                            'ends_at',
                            '>',
                            $startsAt->format(
                                'H:i:s'
                            )
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $customerConflict !== null
                ) {
                    throw ValidationException::withMessages([
                        'starts_at' =>
                            'You already have another appointment that overlaps this time.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Create Appointment
                |--------------------------------------------------------------------------
                |
                | Important values are generated from trusted server data.
                |
                | We never trust the client for:
                |
                | - customer_id
                | - shop ownership
                | - end time
                | - service name snapshot
                | - price snapshot
                | - duration snapshot
                | - appointment status
                |
                */

                $appointment =
                    new Appointment([
                        'appointment_date' =>
                            $startsAt
                                ->toDateString(),

                        'starts_at' =>
                            $startsAt
                                ->format(
                                    'H:i:s'
                                ),

                        'notes' =>
                            $data['notes']
                            ?? null,
                    ]);

                $appointment->customer_id =
                    $lockedCustomer->id;

                $appointment->shop_id =
                    $shop->id;

                $appointment->service_id =
                    $service->id;

                $appointment->ends_at =
                    $endsAt->format(
                        'H:i:s'
                    );

                $appointment->service_name =
                    $service->name;

                $appointment->price_snapshot =
                    $service->price;

                $appointment
                    ->duration_minutes_snapshot =
                    $service->duration_minutes;

                $appointment->status =
                    AppointmentStatus::Pending;

                $appointment->save();

                /*
                |--------------------------------------------------------------------------
                | Return Appointment
                |--------------------------------------------------------------------------
                */

                return $appointment->load([
                    'shop',
                    'service',
                ]);
            },

            /*
            |--------------------------------------------------------------------------
            | Deadlock Retry
            |--------------------------------------------------------------------------
            |
            | Laravel may retry the transaction up to three times if the
            | database encounters a deadlock.
            |
            */

            3
        );
    }
}