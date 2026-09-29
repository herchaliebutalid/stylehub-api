<?php

namespace App\Services\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\RescheduleRequestStatus;
use App\Models\Appointment;
use App\Models\Shop;
use App\Models\User;
use App\Services\Notifications\AppointmentNoShowNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShopOwnerAppointmentWorkflowService
{
    public function __construct(
        private readonly AppointmentNoShowNotificationService $noShowNotifications
    ) {}

    /**
     * Confirm a pending appointment.
     */
    public function confirm(
        Shop $shop,
        User $owner,
        int $appointmentId
    ): Appointment {
        return DB::transaction(
            function () use (
                $shop,
                $owner,
                $appointmentId
            ): Appointment {
                $appointment =
                    $this->lockedOwnedAppointment(
                        $shop,
                        $appointmentId
                    );

                if (
                    $appointment->status
                    !== AppointmentStatus::Pending
                ) {
                    $this->conflict(
                        'Only a pending appointment can be confirmed.'
                    );
                }

                $startsAt =
                    $this->appointmentStart(
                        $appointment
                    );

                if (
                    ! $startsAt->isFuture()
                ) {
                    $this->conflict(
                        'A past appointment cannot be confirmed.'
                    );
                }

                $appointment->status =
                    AppointmentStatus::Confirmed;

                $appointment->save();

                return $this->loadAppointment(
                    $appointment
                );
            }
        );
    }

    /**
     * Decline a pending appointment.
     */
    public function decline(
        Shop $shop,
        User $owner,
        int $appointmentId,
        string $reason
    ): Appointment {
        return DB::transaction(
            function () use (
                $shop,
                $owner,
                $appointmentId,
                $reason
            ): Appointment {
                $appointment =
                    $this->lockedOwnedAppointment(
                        $shop,
                        $appointmentId
                    );

                if (
                    $appointment->status
                    !== AppointmentStatus::Pending
                ) {
                    $this->conflict(
                        'Only a pending appointment can be declined.'
                    );
                }

                $appointment->status =
                    AppointmentStatus::Declined;

                $appointment->cancellation_reason =
                    $reason;

                $appointment->cancelled_by =
                    $owner->id;

                $appointment->cancelled_at =
                    now();

                $appointment->save();

                return $this->loadAppointment(
                    $appointment
                );
            }
        );
    }

    /**
     * Cancel a confirmed appointment before it begins.
     */
    public function cancel(
        Shop $shop,
        User $owner,
        int $appointmentId,
        string $reason
    ): Appointment {
        return DB::transaction(
            function () use (
                $shop,
                $owner,
                $appointmentId,
                $reason
            ): Appointment {
                $appointment =
                    $this->lockedOwnedAppointment(
                        $shop,
                        $appointmentId
                    );

                if (
                    $appointment->status
                    !== AppointmentStatus::Confirmed
                ) {
                    $this->conflict(
                        'Only a confirmed appointment can be cancelled by the shop.'
                    );
                }

                $startsAt =
                    $this->appointmentStart(
                        $appointment
                    );

                if (
                    ! $startsAt->isFuture()
                ) {
                    $this->conflict(
                        'An appointment cannot be cancelled by the shop after it has started.'
                    );
                }

                $appointment->status =
                    AppointmentStatus::Cancelled;

                $appointment->cancellation_reason =
                    $reason;

                $appointment->cancelled_by =
                    $owner->id;

                $appointment->cancelled_at =
                    now();

                $appointment->save();

                return $this->loadAppointment(
                    $appointment
                );
            }
        );
    }

    /**
     * Complete a confirmed appointment after its scheduled end.
     */
    public function complete(
        Shop $shop,
        int $appointmentId
    ): Appointment {
        return DB::transaction(
            function () use (
                $shop,
                $appointmentId
            ): Appointment {
                $appointment =
                    $this->lockedOwnedAppointment(
                        $shop,
                        $appointmentId
                    );

                if (
                    $appointment->status
                    !== AppointmentStatus::Confirmed
                ) {
                    $this->conflict(
                        'Only a confirmed appointment can be completed.'
                    );
                }

                $endsAt =
                    $this->appointmentEnd(
                        $appointment
                    );

                if ($endsAt->isFuture()) {
                    $this->conflict(
                        'The appointment cannot be completed before its scheduled ending time.'
                    );
                }

                $appointment->status =
                    AppointmentStatus::Completed;

                $appointment->completed_at =
                    now();

                $appointment->save();

                return $this->loadAppointment(
                    $appointment
                );
            }
        );
    }

    /**
     * Mark a confirmed appointment as No-show once its
     * scheduled start time has been reached.
     */
    public function markNoShow(
        Shop $shop,
        int $appointmentId
    ): Appointment {
        $appointment =
            DB::transaction(
                function () use (
                    $shop,
                    $appointmentId
                ): Appointment {
                    $appointment =
                        $this->lockedOwnedAppointment(
                            $shop,
                            $appointmentId
                        );

                    if (
                        $appointment->status
                        !== AppointmentStatus::Confirmed
                    ) {
                        $this->conflict(
                            'Only a confirmed appointment can be marked as a no-show.'
                        );
                    }

                    $startsAt =
                        $this->appointmentStart(
                            $appointment
                        );

                    if ($startsAt->isFuture()) {
                        $this->conflict(
                            'The appointment cannot be marked as a no-show before its scheduled start time.'
                        );
                    }

                    $appointment->status =
                        AppointmentStatus::NoShow;

                    $appointment->no_show_at =
                        now();

                    $appointment->save();

                    /*
                    |--------------------------------------------------------------------------
                    | Pending Reschedule Cleanup
                    |--------------------------------------------------------------------------
                    |
                    | A terminal No-show appointment must not retain an actionable
                    | pending reschedule request.
                    |
                    */

                    $appointment
                        ->rescheduleRequests()
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

                    return $this->loadAppointment(
                        $appointment
                    );
                }
            );

        /*
         * Notification delivery occurs after the transaction.
         * Notification failure cannot roll back No-show.
         */
        $this->noShowNotifications
            ->notifyCustomer(
                $appointment
            );

        return $appointment;
    }

    /**
     * Retrieve an appointment only from the authenticated
     * owner's shop and lock it for the transition.
     */
    private function lockedOwnedAppointment(
        Shop $shop,
        int $appointmentId
    ): Appointment {
        return Appointment::query()
            ->where(
                'shop_id',
                $shop->id
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

    private function appointmentEnd(
        Appointment $appointment
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            $appointment
                ->appointment_date
                ->toDateString()
            .' '
            .$appointment->ends_at,
            config(
                'app.timezone',
                'UTC'
            )
        );
    }

    private function loadAppointment(
        Appointment $appointment
    ): Appointment {
        return $appointment->load([
            'customer',
            'service',
        ]);
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