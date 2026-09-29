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

class ShopOwnerRescheduleWorkflowService
{
    /**
     * Approve a pending reschedule request.
     *
     * The requested slot is revalidated under database locks because its
     * availability may have changed since the customer submitted the request.
     */
    public function approve(
        Shop $shop,
        User $owner,
        int $requestId
    ): RescheduleRequest {
        return DB::transaction(
            function () use (
                $shop,
                $owner,
                $requestId
            ): RescheduleRequest {
                /*
                |--------------------------------------------------------------------------
                | Preliminary Tenant Lookup
                |--------------------------------------------------------------------------
                |
                | This read gives us the customer ID needed for the global lock
                | order. Nothing is changed based on this unlocked copy.
                |
                */

                $candidate =
                    RescheduleRequest::query()
                        ->whereKey(
                            $requestId
                        )
                        ->whereHas(
                            'appointment',
                            fn ($query) =>
                                $query->where(
                                    'shop_id',
                                    $shop->id
                                )
                        )
                        ->with([
                            'appointment:id,customer_id,shop_id',
                        ])
                        ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | Global Lock Order
                |--------------------------------------------------------------------------
                |
                | customer → shop → appointment → reschedule request
                |
                | Booking uses customer → shop as well, reducing deadlock risk
                | and protecting cross-shop customer availability.
                |
                */

                $customer = User::query()
                    ->whereKey(
                        $candidate
                            ->appointment
                            ->customer_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedShop = Shop::query()
                    ->whereKey(
                        $shop->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $appointment =
                    Appointment::query()
                        ->whereKey(
                            $candidate
                                ->appointment_id
                        )
                        ->where(
                            'shop_id',
                            $lockedShop->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $rescheduleRequest =
                    RescheduleRequest::query()
                        ->whereKey(
                            $requestId
                        )
                        ->where(
                            'appointment_id',
                            $appointment->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | Recheck Request State
                |--------------------------------------------------------------------------
                */

                if (
                    $rescheduleRequest->status
                    !== RescheduleRequestStatus::Pending
                ) {
                    $this->conflict(
                        'Only a pending reschedule request can be approved.'
                    );
                }

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
                        'The appointment can no longer be rescheduled.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Original Appointment Must Still Be Future
                |--------------------------------------------------------------------------
                */

                if (
                    ! $this
                        ->appointmentStart(
                            $appointment
                        )
                        ->isFuture()
                ) {
                    $this->conflict(
                        'The original appointment has already started.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Shop Must Still Be Bookable
                |--------------------------------------------------------------------------
                */

                if (! $lockedShop->isApproved()) {
                    throw ValidationException::withMessages([
                        'requested_date' =>
                            'The shop is no longer approved for appointments.',
                    ]);
                }

                if (
                    ! $lockedShop
                        ->availability_status
                        ->acceptsAppointments()
                ) {
                    throw ValidationException::withMessages([
                        'requested_date' =>
                            'The shop is not currently accepting appointments.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Recalculate Requested Slot
                |--------------------------------------------------------------------------
                |
                | Appointment duration snapshot remains authoritative even if
                | the current Service record has since been edited.
                |
                */

                $timezone = config(
                    'app.timezone',
                    'UTC'
                );

                $requestedStart =
                    CarbonImmutable::parse(
                        $rescheduleRequest
                            ->requested_date
                            ->toDateString()
                        .' '
                        .$rescheduleRequest
                            ->requested_starts_at,
                        $timezone
                    );

                if (
                    ! $requestedStart->isFuture()
                ) {
                    throw ValidationException::withMessages([
                        'requested_starts_at' =>
                            'The requested reschedule time has already passed.',
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
                            'The requested time must differ from the current appointment time.',
                    ]);
                }

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

                /*
                |--------------------------------------------------------------------------
                | Recheck Shop Schedule
                |--------------------------------------------------------------------------
                */

                $schedule =
                    $lockedShop->schedules()
                        ->where(
                            'day_of_week',
                            $requestedStart
                                ->isoWeekday()
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
                |--------------------------------------------------------------------------
                | Recheck Shop Conflict
                |--------------------------------------------------------------------------
                */

                $shopConflict =
                    Appointment::query()
                        ->where(
                            'shop_id',
                            $lockedShop->id
                        )
                        ->where(
                            'id',
                            '!=',
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
                        ->lockForUpdate()
                        ->first();

                if ($shopConflict !== null) {
                    throw ValidationException::withMessages([
                        'requested_starts_at' =>
                            'The requested appointment time is no longer available.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Recheck Customer Conflict
                |--------------------------------------------------------------------------
                |
                | The customer row has already been locked, so another booking
                | by this customer cannot race this approval.
                |
                */

                $customerConflict =
                    Appointment::query()
                        ->where(
                            'customer_id',
                            $customer->id
                        )
                        ->where(
                            'id',
                            '!=',
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
                        ->lockForUpdate()
                        ->first();

                if ($customerConflict !== null) {
                    throw ValidationException::withMessages([
                        'requested_starts_at' =>
                            'The customer now has another appointment that overlaps the requested time.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Move Appointment
                |--------------------------------------------------------------------------
                |
                | Its current status is preserved:
                |
                | pending   → pending
                | confirmed → confirmed
                |
                */

                $appointment->appointment_date =
                    $requestedStart
                        ->toDateString();

                $appointment->starts_at =
                    $requestedStart
                        ->format(
                            'H:i:s'
                        );

                $appointment->ends_at =
                    $requestedEnd
                        ->format(
                            'H:i:s'
                        );

                $appointment->save();

                /*
                |--------------------------------------------------------------------------
                | Mark Request Approved
                |--------------------------------------------------------------------------
                */

                $rescheduleRequest
                    ->requested_ends_at =
                    $requestedEnd
                        ->format(
                            'H:i:s'
                        );

                $rescheduleRequest->status =
                    RescheduleRequestStatus::Approved;

                $rescheduleRequest
                    ->rejection_reason =
                    null;

                $rescheduleRequest->reviewed_by =
                    $owner->id;

                $rescheduleRequest->reviewed_at =
                    now();

                $rescheduleRequest->save();

                return $rescheduleRequest->load([
                    'appointment.customer',
                    'reviewer',
                ]);
            },
            3
        );
    }

    /**
     * Reject one pending request without changing the appointment slot.
     */
    public function reject(
        Shop $shop,
        User $owner,
        int $requestId,
        string $reason
    ): RescheduleRequest {
        return DB::transaction(
            function () use (
                $shop,
                $owner,
                $requestId,
                $reason
            ): RescheduleRequest {
                /*
                 * Resolve the request inside the authenticated shop's tenant.
                 */
                $candidate =
                    RescheduleRequest::query()
                        ->whereKey(
                            $requestId
                        )
                        ->whereHas(
                            'appointment',
                            fn ($query) =>
                                $query->where(
                                    'shop_id',
                                    $shop->id
                                )
                        )
                        ->firstOrFail();

                /*
                 * Appointment is locked before the request. This matches the
                 * order used by customer cancellation, avoiding an
                 * appointment/request lock-order inversion.
                 */
                $appointment =
                    Appointment::query()
                        ->whereKey(
                            $candidate
                                ->appointment_id
                        )
                        ->where(
                            'shop_id',
                            $shop->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $rescheduleRequest =
                    RescheduleRequest::query()
                        ->whereKey(
                            $candidate->id
                        )
                        ->where(
                            'appointment_id',
                            $appointment->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $rescheduleRequest->status
                    !== RescheduleRequestStatus::Pending
                ) {
                    $this->conflict(
                        'Only a pending reschedule request can be rejected.'
                    );
                }

                $rescheduleRequest->status =
                    RescheduleRequestStatus::Rejected;

                $rescheduleRequest
                    ->rejection_reason =
                    $reason;

                $rescheduleRequest->reviewed_by =
                    $owner->id;

                $rescheduleRequest->reviewed_at =
                    now();

                $rescheduleRequest->save();

                return $rescheduleRequest->load([
                    'appointment.customer',
                    'reviewer',
                ]);
            },
            3
        );
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