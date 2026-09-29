<?php

namespace App\Services\Notifications;

use App\Enums\InAppNotificationType;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\InAppNotification;
use App\Models\RescheduleRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Throwable;

class InAppNotificationService
{
    public function bookingReceived(
        Appointment $appointment
    ): ?InAppNotification {
        $appointment->loadMissing([
            'customer',
            'shop.owner',
        ]);

        $recipient =
            $appointment->shop?->owner;

        if (! $recipient instanceof User) {
            return null;
        }

        $customerName =
            $appointment->customer?->full_name
            ?: 'A customer';

        $message =
            sprintf(
                '%s booked %s for %s at %s.',
                $customerName,
                $appointment->service_name,
                $this->formatAppointmentDate(
                    $appointment
                ),
                $this->formatAppointmentTime(
                    $appointment
                )
            );

        return $this->createSafely(
            recipient:
                $recipient,

            type:
                InAppNotificationType::BookingReceived,

            message:
                $message,

            appointment:
                $appointment,

            dedupeKey:
                $this->appointmentDedupeKey(
                    $appointment,
                    InAppNotificationType::BookingReceived,
                    $recipient
                )
        );
    }

    public function appointmentConfirmed(
        Appointment $appointment
    ): ?InAppNotification {
        $appointment->loadMissing([
            'customer',
            'shop',
        ]);

        $recipient =
            $appointment->customer;

        if (! $recipient instanceof User) {
            return null;
        }

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        return $this->createSafely(
            recipient:
                $recipient,

            type:
                InAppNotificationType::AppointmentConfirmed,

            message:
                sprintf(
                    'Your appointment at %s on %s at %s was confirmed.',
                    $shopName,
                    $this->formatAppointmentDate(
                        $appointment
                    ),
                    $this->formatAppointmentTime(
                        $appointment
                    )
                ),

            appointment:
                $appointment,

            dedupeKey:
                $this->appointmentDedupeKey(
                    $appointment,
                    InAppNotificationType::AppointmentConfirmed,
                    $recipient
                )
        );
    }

    public function appointmentDeclined(
        Appointment $appointment
    ): ?InAppNotification {
        $appointment->loadMissing([
            'customer',
            'shop',
        ]);

        $recipient =
            $appointment->customer;

        if (! $recipient instanceof User) {
            return null;
        }

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        return $this->createSafely(
            recipient:
                $recipient,

            type:
                InAppNotificationType::AppointmentDeclined,

            message:
                sprintf(
                    'Your appointment request at %s on %s at %s was declined. Open StyleHub for details.',
                    $shopName,
                    $this->formatAppointmentDate(
                        $appointment
                    ),
                    $this->formatAppointmentTime(
                        $appointment
                    )
                ),

            appointment:
                $appointment,

            dedupeKey:
                $this->appointmentDedupeKey(
                    $appointment,
                    InAppNotificationType::AppointmentDeclined,
                    $recipient
                )
        );
    }

    public function appointmentCancelled(
        Appointment $appointment
    ): ?InAppNotification {
        $appointment->loadMissing([
            'customer',
            'shop.owner',
        ]);

        $customer =
            $appointment->customer;

        $owner =
            $appointment->shop?->owner;

        if (! $customer instanceof User) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Customer Cancelled
        |--------------------------------------------------------------------------
        |
        | The customer already knows about their own action, so notify the
        | shop owner instead.
        |
        */

        if (
            $appointment->cancelled_by !== null
            &&
            (int) $appointment->cancelled_by
                === (int) $customer->id
        ) {
            if (! $owner instanceof User) {
                return null;
            }

            $customerName =
                $customer->full_name
                ?: 'A customer';

            return $this->createSafely(
                recipient:
                    $owner,

                type:
                    InAppNotificationType::AppointmentCancelled,

                message:
                    sprintf(
                        '%s cancelled their %s appointment for %s at %s.',
                        $customerName,
                        $appointment->service_name,
                        $this->formatAppointmentDate(
                            $appointment
                        ),
                        $this->formatAppointmentTime(
                            $appointment
                        )
                    ),

                appointment:
                    $appointment,

                dedupeKey:
                    $this->appointmentDedupeKey(
                        $appointment,
                        InAppNotificationType::AppointmentCancelled,
                        $owner
                    )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shop / System Cancelled
        |--------------------------------------------------------------------------
        */

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        return $this->createSafely(
            recipient:
                $customer,

            type:
                InAppNotificationType::AppointmentCancelled,

            message:
                sprintf(
                    'Your appointment at %s on %s at %s was cancelled. Open StyleHub for details.',
                    $shopName,
                    $this->formatAppointmentDate(
                        $appointment
                    ),
                    $this->formatAppointmentTime(
                        $appointment
                    )
                ),

            appointment:
                $appointment,

            dedupeKey:
                $this->appointmentDedupeKey(
                    $appointment,
                    InAppNotificationType::AppointmentCancelled,
                    $customer
                )
        );
    }

    public function appointmentCompleted(
        Appointment $appointment
    ): ?InAppNotification {
        $appointment->loadMissing([
            'customer',
            'shop',
        ]);

        $recipient =
            $appointment->customer;

        if (! $recipient instanceof User) {
            return null;
        }

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        return $this->createSafely(
            recipient:
                $recipient,

            type:
                InAppNotificationType::AppointmentCompleted,

            message:
                sprintf(
                    'Your appointment at %s is complete. You can now leave a review in StyleHub.',
                    $shopName
                ),

            appointment:
                $appointment,

            dedupeKey:
                $this->appointmentDedupeKey(
                    $appointment,
                    InAppNotificationType::AppointmentCompleted,
                    $recipient
                )
        );
    }

    public function rescheduleRequested(
        RescheduleRequest $rescheduleRequest
    ): ?InAppNotification {
        $rescheduleRequest->loadMissing([
            'appointment.customer',
            'appointment.shop.owner',
        ]);

        $appointment =
            $rescheduleRequest->appointment;

        if (! $appointment instanceof Appointment) {
            return null;
        }

        $recipient =
            $appointment->shop?->owner;

        if (! $recipient instanceof User) {
            return null;
        }

        $customerName =
            $appointment->customer?->full_name
            ?: 'A customer';

        return $this->createSafely(
            recipient:
                $recipient,

            type:
                InAppNotificationType::RescheduleRequested,

            message:
                sprintf(
                    '%s requested to reschedule %s to %s at %s.',
                    $customerName,
                    $appointment->service_name,
                    $this->formatRequestedDate(
                        $rescheduleRequest
                    ),
                    $this->formatTime(
                        $rescheduleRequest->requested_starts_at
                    )
                ),

            appointment:
                $appointment,

            rescheduleRequest:
                $rescheduleRequest,

            dedupeKey:
                $this->rescheduleDedupeKey(
                    $rescheduleRequest,
                    InAppNotificationType::RescheduleRequested,
                    $recipient
                )
        );
    }

    public function rescheduleApproved(
        RescheduleRequest $rescheduleRequest
    ): ?InAppNotification {
        $rescheduleRequest->loadMissing([
            'appointment.customer',
            'appointment.shop',
        ]);

        $appointment =
            $rescheduleRequest->appointment;

        if (! $appointment instanceof Appointment) {
            return null;
        }

        $recipient =
            $appointment->customer;

        if (! $recipient instanceof User) {
            return null;
        }

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        return $this->createSafely(
            recipient:
                $recipient,

            type:
                InAppNotificationType::RescheduleApproved,

            message:
                sprintf(
                    'Your reschedule request was approved. Your appointment at %s is now %s at %s.',
                    $shopName,
                    $this->formatAppointmentDate(
                        $appointment
                    ),
                    $this->formatAppointmentTime(
                        $appointment
                    )
                ),

            appointment:
                $appointment,

            rescheduleRequest:
                $rescheduleRequest,

            dedupeKey:
                $this->rescheduleDedupeKey(
                    $rescheduleRequest,
                    InAppNotificationType::RescheduleApproved,
                    $recipient
                )
        );
    }

    public function rescheduleRejected(
        RescheduleRequest $rescheduleRequest
    ): ?InAppNotification {
        $rescheduleRequest->loadMissing([
            'appointment.customer',
            'appointment.shop',
        ]);

        $appointment =
            $rescheduleRequest->appointment;

        if (! $appointment instanceof Appointment) {
            return null;
        }

        $recipient =
            $appointment->customer;

        if (! $recipient instanceof User) {
            return null;
        }

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        return $this->createSafely(
            recipient:
                $recipient,

            type:
                InAppNotificationType::RescheduleRejected,

            message:
                sprintf(
                    'Your reschedule request for %s at %s at %s was not approved. Your current appointment remains unchanged.',
                    $this->formatRequestedDate(
                        $rescheduleRequest
                    ),
                    $this->formatTime(
                        $rescheduleRequest->requested_starts_at
                    ),
                    $shopName
                ),

            appointment:
                $appointment,

            rescheduleRequest:
                $rescheduleRequest,

            dedupeKey:
                $this->rescheduleDedupeKey(
                    $rescheduleRequest,
                    InAppNotificationType::RescheduleRejected,
                    $recipient
                )
        );
    }

    public function reviewReceived(
        Feedback $feedback
    ): ?InAppNotification {
        $feedback->loadMissing([
            'customer',
            'shop.owner',
            'appointment',
        ]);

        $recipient =
            $feedback->shop?->owner;

        if (! $recipient instanceof User) {
            return null;
        }

        $customerName =
            $feedback->customer?->full_name
            ?: 'A customer';

        return $this->createSafely(
            recipient:
                $recipient,

            type:
                InAppNotificationType::ReviewReceived,

            message:
                sprintf(
                    '%s left a %d-star review for your shop.',
                    $customerName,
                    (int) $feedback->rating
                ),

            appointment:
                $feedback->appointment,

            feedback:
                $feedback,

            dedupeKey:
                sprintf(
                    'inapp:feedback:%d:received:user:%d',
                    $feedback->id,
                    $recipient->id
                )
        );
    }

    /**
     * Persist one trusted notification.
     *
     * Notification infrastructure must never invalidate an already committed
     * booking, reschedule, cancellation or review workflow.
     */
    private function createSafely(
        User $recipient,
        InAppNotificationType $type,
        string $message,
        ?Appointment $appointment = null,
        ?RescheduleRequest $rescheduleRequest = null,
        ?Feedback $feedback = null,
        ?string $dedupeKey = null
    ): ?InAppNotification {
        try {
            /*
            |--------------------------------------------------------------------------
            | Existing Notification
            |--------------------------------------------------------------------------
            */

            if ($dedupeKey !== null) {
                $existing =
                    InAppNotification::query()
                        ->where(
                            'dedupe_key',
                            $dedupeKey
                        )
                        ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Trusted Content
            |--------------------------------------------------------------------------
            */

            $notification =
                new InAppNotification([
                    'type' =>
                        $type,

                    'title' =>
                        $type->label(),

                    'message' =>
                        trim(
                            $message
                        ),
                ]);

            /*
            |--------------------------------------------------------------------------
            | Trusted Ownership / Source References
            |--------------------------------------------------------------------------
            |
            | These fields are intentionally not fillable on the model.
            |
            */

            $notification->user_id =
                $recipient->id;

            $notification->appointment_id =
                $appointment?->id;

            $notification->reschedule_request_id =
                $rescheduleRequest?->id;

            $notification->feedback_id =
                $feedback?->id;

            $notification->promo_id =
                null;

            $notification->dedupe_key =
                $dedupeKey;

            $notification->read_at =
                null;

            try {
                $notification->save();
            } catch (QueryException $exception) {
                /*
                 * The UNIQUE database constraint remains the final protection
                 * against two workers creating the same event simultaneously.
                 */

                if ($dedupeKey !== null) {
                    $existing =
                        InAppNotification::query()
                            ->where(
                                'dedupe_key',
                                $dedupeKey
                            )
                            ->first();

                    if ($existing !== null) {
                        return $existing;
                    }
                }

                throw $exception;
            }

            return $notification;
        } catch (Throwable $exception) {
            /*
             * A notification problem must never roll back or invalidate the
             * already completed StyleHub business operation.
             */

            report(
                $exception
            );

            return null;
        }
    }

    private function appointmentDedupeKey(
        Appointment $appointment,
        InAppNotificationType $type,
        User $recipient
    ): string {
        return sprintf(
            'inapp:appointment:%d:%s:user:%d',
            $appointment->id,
            $type->value,
            $recipient->id
        );
    }

    private function rescheduleDedupeKey(
        RescheduleRequest $rescheduleRequest,
        InAppNotificationType $type,
        User $recipient
    ): string {
        return sprintf(
            'inapp:reschedule:%d:%s:user:%d',
            $rescheduleRequest->id,
            $type->value,
            $recipient->id
        );
    }

    private function formatAppointmentDate(
        Appointment $appointment
    ): string {
        if ($appointment->appointment_date === null) {
            return 'the scheduled date';
        }

        return $appointment
            ->appointment_date
            ->format(
                'M j, Y'
            );
    }

    private function formatRequestedDate(
        RescheduleRequest $rescheduleRequest
    ): string {
        if ($rescheduleRequest->requested_date === null) {
            return 'the requested date';
        }

        return $rescheduleRequest
            ->requested_date
            ->format(
                'M j, Y'
            );
    }

    private function formatAppointmentTime(
        Appointment $appointment
    ): string {
        return $this->formatTime(
            $appointment->starts_at
        );
    }

    private function formatTime(
        ?string $time
    ): string {
        if (
            $time === null
            ||
            trim(
                $time
            ) === ''
        ) {
            return 'the scheduled time';
        }

        try {
            return CarbonImmutable::createFromFormat(
                'H:i:s',
                $time,
                config(
                    'app.timezone',
                    'UTC'
                )
            )->format(
                'g:i A'
            );
        } catch (Throwable) {
            return $time;
        }
    }
}