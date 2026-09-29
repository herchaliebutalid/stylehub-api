<?php

namespace App\Services\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\RescheduleRequestStatus;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerAppointmentWorkflowService
{
    /**
     * Cancel one future appointment owned by the authenticated customer.
     */
    public function cancel(
        User $customer,
        int $appointmentId,
        string $reason
    ): Appointment {
        return DB::transaction(
            function () use (
                $customer,
                $appointmentId,
                $reason
            ): Appointment {
                $appointment =
                    $this->lockedOwnedAppointment(
                        $customer,
                        $appointmentId
                    );

                if (
                    ! in_array(
                        $appointment->status,
                        [
                            AppointmentStatus::Pending,
                            AppointmentStatus::Confirmed,
                        ],
                        true
                    )
                ) {
                    $this->conflict(
                        'This appointment can no longer be cancelled.'
                    );
                }

                if (
                    ! $this->appointmentStart(
                        $appointment
                    )->isFuture()
                ) {
                    $this->conflict(
                        'An appointment cannot be cancelled after it has started.'
                    );
                }

                $appointment->status =
                    AppointmentStatus::Cancelled;

                $appointment->cancellation_reason =
                    $reason;

                $appointment->cancelled_by =
                    $customer->id;

                $appointment->cancelled_at =
                    now();

                $appointment->save();

                /*
                 * Any still-pending reschedule request is no longer meaningful
                 * once the original appointment has been cancelled.
                 */
                $appointment->rescheduleRequests()
                    ->where(
                        'status',
                        RescheduleRequestStatus::Pending
                            ->value
                    )
                    ->update([
                        'status' =>
                            RescheduleRequestStatus::Withdrawn
                                ->value,

                        'updated_at' =>
                            now(),
                    ]);

                return $appointment->load([
                    'shop',
                    'service',
                ]);
            }
        );
    }

    /**
     * Submit a requested replacement slot.
     *
     * The requested slot is validated now for good user feedback, but it is
     * NOT reserved. Step 11E will revalidate the slot while approving it.
     *
     * @param array<string, mixed> $data
     */
    public function requestReschedule(
        User $customer,
        int $appointmentId,
        array $data
    ): RescheduleRequest {
        return DB::transaction(
            function () use (
                $customer,
                $appointmentId,
                $data
            ): RescheduleRequest {
                /*
                 * Locking the appointment serializes simultaneous reschedule
                 * requests for the same appointment.
                 */
                $appointment =
                    $this->lockedOwnedAppointment(
                        $customer,
                        $appointmentId
                    );

                if (
                    ! in_array(
                        $appointment->status,
                        [
                            AppointmentStatus::Pending,
                            AppointmentStatus::Confirmed,
                        ],
                        true
                    )
                ) {
                    $this->conflict(
                        'This appointment can no longer be rescheduled.'
                    );
                }

                if (
                    ! $this->appointmentStart(
                        $appointment
                    )->isFuture()
                ) {
                    $this->conflict(
                        'An appointment cannot be rescheduled after it has started.'
                    );
                }

                $existingPending =
                    $appointment
                        ->rescheduleRequests()
                        ->where(
                            'status',
                            RescheduleRequestStatus::Pending
                                ->value
                        )
                        ->exists();

                if ($existingPending) {
                    $this->conflict(
                        'A pending reschedule request already exists for this appointment.'
                    );
                }

                $timezone = config(
                    'app.timezone',
                    'UTC'
                );

                $requestedStart =
                    CarbonImmutable::parse(
                        $data['requested_date']
                        .' '
                        .$data['requested_starts_at']
                        .':00',
                        $timezone
                    );

                if (
                    ! $requestedStart->isAfter(
                        CarbonImmutable::now(
                            $timezone
                        )
                    )
                ) {
                    throw ValidationException::withMessages([
                        'requested_starts_at' =>
                            'The requested appointment time must be in the future.',
                    ]);
                }

                $currentStart =
                    $this->appointmentStart(
                        $appointment
                    );

                if (
                    $requestedStart->equalTo(
                        $currentStart
                    )
                ) {
                    throw ValidationException::withMessages([
                        'requested_starts_at' =>
                            'The requested time must be different from the current appointment time.',
                    ]);
                }

                /*
                 * Use the historical booked duration rather than whatever
                 * duration the service may have been changed to later.
                 */
                $requestedEnd =
                    $requestedStart->addMinutes(
                        $appointment
                            ->duration_minutes_snapshot
                    );

                if (
                    $requestedEnd->toDateString()
                    !== $requestedStart->toDateString()
                ) {
                    throw ValidationException::withMessages([
                        'requested_starts_at' =>
                            'The requested appointment would extend beyond the operating day.',
                    ]);
                }

                $shop = Shop::query()
                    ->whereKey(
                        $appointment->shop_id
                    )
                    ->firstOrFail();

                if (! $shop->isApproved()) {
                    throw ValidationException::withMessages([
                        'requested_date' =>
                            'This shop is not currently available for rescheduling.',
                    ]);
                }

                if (
                    ! $shop
                        ->availability_status
                        ->acceptsAppointments()
                ) {
                    throw ValidationException::withMessages([
                        'requested_date' =>
                            'This shop is not currently accepting appointment requests.',
                    ]);
                }

                $schedule = $shop->schedules()
                    ->where(
                        'day_of_week',
                        $requestedStart->isoWeekday()
                    )
                    ->first();

                if (
                    $schedule === null
                    || ! $schedule->isOpen()
                    || ! $schedule->hasOperatingHours()
                ) {
                    throw ValidationException::withMessages([
                        'requested_date' =>
                            'The shop is closed on the requested day.',
                    ]);
                }

                $opensAt =
                    CarbonImmutable::parse(
                        $requestedStart
                            ->toDateString()
                        .' '
                        .$schedule->opens_at,
                        $timezone
                    );

                $closesAt =
                    CarbonImmutable::parse(
                        $requestedStart
                            ->toDateString()
                        .' '
                        .$schedule->closes_at,
                        $timezone
                    );

                if (
                    $requestedStart->lessThan(
                        $opensAt
                    )
                    || $requestedEnd->greaterThan(
                        $closesAt
                    )
                ) {
                    throw ValidationException::withMessages([
                        'requested_starts_at' =>
                            'The requested appointment must fit completely within shop operating hours.',
                    ]);
                }

                $blockingStatuses = [
                    AppointmentStatus::Pending->value,
                    AppointmentStatus::Confirmed->value,
                ];

                /*
                 * The current appointment itself is excluded because the
                 * customer is asking to move that same appointment.
                 */
                $shopConflict =
                    Appointment::query()
                        ->where(
                            'shop_id',
                            $shop->id
                        )
                        ->whereKeyNot(
                            $appointment->id
                        )
                        ->whereDate(
                            'appointment_date',
                            $requestedStart
                                ->toDateString()
                        )
                        ->whereIn(
                            'status',
                            $blockingStatuses
                        )
                        ->where(
                            'starts_at',
                            '<',
                            $requestedEnd->format(
                                'H:i:s'
                            )
                        )
                        ->where(
                            'ends_at',
                            '>',
                            $requestedStart->format(
                                'H:i:s'
                            )
                        )
                        ->first();

                if ($shopConflict !== null) {
                    throw ValidationException::withMessages([
                        'requested_starts_at' =>
                            'The requested appointment time is not currently available.',
                    ]);
                }

                $customerConflict =
                    Appointment::query()
                        ->where(
                            'customer_id',
                            $customer->id
                        )
                        ->whereKeyNot(
                            $appointment->id
                        )
                        ->whereDate(
                            'appointment_date',
                            $requestedStart
                                ->toDateString()
                        )
                        ->whereIn(
                            'status',
                            $blockingStatuses
                        )
                        ->where(
                            'starts_at',
                            '<',
                            $requestedEnd->format(
                                'H:i:s'
                            )
                        )
                        ->where(
                            'ends_at',
                            '>',
                            $requestedStart->format(
                                'H:i:s'
                            )
                        )
                        ->first();

                if ($customerConflict !== null) {
                    throw ValidationException::withMessages([
                        'requested_starts_at' =>
                            'You already have another appointment that overlaps the requested time.',
                    ]);
                }

                $request =
                    new RescheduleRequest([
                        'requested_date' =>
                            $requestedStart
                                ->toDateString(),

                        'requested_starts_at' =>
                            $requestedStart->format(
                                'H:i:s'
                            ),

                        'customer_reason' =>
                            $data['reason'] ?? null,
                    ]);

                $request->appointment_id =
                    $appointment->id;

                $request->requested_ends_at =
                    $requestedEnd->format(
                        'H:i:s'
                    );

                $request->status =
                    RescheduleRequestStatus::Pending;

                $request->save();

                return $request->load([
                    'appointment.shop',
                ]);
            }
        );
    }

    /**
     * Customer withdraws their own pending reschedule request.
     */
    public function withdraw(
        User $customer,
        int $requestId
    ): RescheduleRequest {
        return DB::transaction(
            function () use (
                $customer,
                $requestId
            ): RescheduleRequest {
                $request =
                    RescheduleRequest::query()
                        ->whereKey(
                            $requestId
                        )
                        ->whereHas(
                            'appointment',
                            fn ($query) =>
                                $query->where(
                                    'customer_id',
                                    $customer->id
                                )
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $request->status
                    !== RescheduleRequestStatus::Pending
                ) {
                    $this->conflict(
                        'Only a pending reschedule request can be withdrawn.'
                    );
                }

                $request->status =
                    RescheduleRequestStatus::Withdrawn;

                $request->save();

                return $request->load([
                    'appointment.shop',
                ]);
            }
        );
    }

    private function lockedOwnedAppointment(
        User $customer,
        int $appointmentId
    ): Appointment {
        return Appointment::query()
            ->where(
                'customer_id',
                $customer->id
            )
            ->whereKey(
                $appointmentId
            )
            ->lockForUpdate()
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
                'UTC'
            )
        );
    }

    private function conflict(
        string $message
    ): never {
        throw new HttpException(
            Response::HTTP_CONFLICT,
            $message
        );
    }
}