<?php

namespace App\Observers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Sms\AppointmentSmsNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class AppointmentObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AppointmentSmsNotifier $smsNotifier,
        private readonly InAppNotificationService $inAppNotifications
    ) {}

    /**
     * A newly created pending appointment represents a customer booking.
     */
    public function created(
        Appointment $appointment
    ): void {
        if (
            $appointment->status
            !== AppointmentStatus::Pending
        ) {
            return;
        }

        /*
         * Existing SMS behavior:
         * acknowledge the booking to the customer.
         */
        $this->smsNotifier
            ->bookingReceived(
                $appointment
            );

        /*
         * New in-app behavior:
         * alert the shop owner about the booking.
         */
        $this->inAppNotifications
            ->bookingReceived(
                $appointment
            );
    }

    /**
     * React only when appointment status itself changes.
     *
     * Editing notes/date/time without a status transition must not create
     * another status notification.
     */
    public function updated(
        Appointment $appointment
    ): void {
        if (
            ! $appointment->wasChanged(
                'status'
            )
        ) {
            return;
        }

        switch ($appointment->status) {
            case AppointmentStatus::Confirmed:
                $this->smsNotifier
                    ->appointmentConfirmed(
                        $appointment
                    );

                $this->inAppNotifications
                    ->appointmentConfirmed(
                        $appointment
                    );

                break;

            case AppointmentStatus::Declined:
                $this->smsNotifier
                    ->appointmentDeclined(
                        $appointment
                    );

                $this->inAppNotifications
                    ->appointmentDeclined(
                        $appointment
                    );

                break;

            case AppointmentStatus::Cancelled:
                $this->smsNotifier
                    ->appointmentCancelled(
                        $appointment
                    );

                $this->inAppNotifications
                    ->appointmentCancelled(
                        $appointment
                    );

                break;

            case AppointmentStatus::Completed:
                /*
                 * Completed appointments intentionally still do not send SMS,
                 * but the customer receives a persistent in-app prompt.
                 */

                $this->inAppNotifications
                    ->appointmentCompleted(
                        $appointment
                    );

                break;

            case AppointmentStatus::Pending:
                break;
        }
    }
}