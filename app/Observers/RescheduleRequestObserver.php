<?php

namespace App\Observers;

use App\Enums\RescheduleRequestStatus;
use App\Models\RescheduleRequest;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Sms\AppointmentSmsNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class RescheduleRequestObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AppointmentSmsNotifier $smsNotifier,
        private readonly InAppNotificationService $inAppNotifications
    ) {}

    /**
     * A newly created pending request needs shop-owner attention.
     */
    public function created(
        RescheduleRequest $rescheduleRequest
    ): void {
        if (
            $rescheduleRequest->status
            !== RescheduleRequestStatus::Pending
        ) {
            return;
        }

        $this->inAppNotifications
            ->rescheduleRequested(
                $rescheduleRequest
            );
    }

    /**
     * Notify the customer only when the reschedule status changes.
     */
    public function updated(
        RescheduleRequest $rescheduleRequest
    ): void {
        if (
            ! $rescheduleRequest->wasChanged(
                'status'
            )
        ) {
            return;
        }

        switch (
            $rescheduleRequest->status
        ) {
            case RescheduleRequestStatus::Approved:
                $this->smsNotifier
                    ->rescheduleApproved(
                        $rescheduleRequest
                    );

                $this->inAppNotifications
                    ->rescheduleApproved(
                        $rescheduleRequest
                    );

                break;

            case RescheduleRequestStatus::Rejected:
                $this->smsNotifier
                    ->rescheduleRejected(
                        $rescheduleRequest
                    );

                $this->inAppNotifications
                    ->rescheduleRejected(
                        $rescheduleRequest
                    );

                break;

            /*
             * Pending creation is handled by created().
             *
             * Customer withdrawal currently does not need another
             * notification.
             */

            case RescheduleRequestStatus::Pending:
            case RescheduleRequestStatus::Withdrawn:
                break;
        }
    }
}