<?php

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Exceptions\Sms\SmsGatewayException;
use App\Models\SmsNotification;
use App\Services\Sms\TextBeeSmsGateway;
use App\Support\AppointmentReminderKey;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendSmsNotification implements ShouldQueue
{
    use Queueable;

    /*
    |--------------------------------------------------------------------------
    | No Automatic Provider Retries
    |--------------------------------------------------------------------------
    */

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public readonly int $smsNotificationId
    ) {
        $this->onQueue(
            'sms'
        );
    }

    public function handle(
        TextBeeSmsGateway $gateway
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Claim Notification
        |--------------------------------------------------------------------------
        */

        $notification =
            DB::transaction(
                function (): ?SmsNotification {
                    $notification =
                        SmsNotification::query()
                            ->whereKey(
                                $this->smsNotificationId
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        $notification === null
                    ) {
                        return null;
                    }

                    /*
                     * Duplicate queue jobs are harmless. Only the first job
                     * that sees Pending may claim the notification.
                     */
                    if (
                        $notification->status
                        !== SmsNotificationStatus::Pending
                    ) {
                        return null;
                    }

                    $notification->status =
                        SmsNotificationStatus::Processing;

                    $notification->attempts =
                        $notification->attempts + 1;

                    $notification->last_attempt_at =
                        now();

                    $notification->failure_kind =
                        null;

                    $notification->last_error =
                        null;

                    $notification->failed_at =
                        null;

                    $notification->save();

                    return $notification;
                }
            );

        if ($notification === null) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Stale Notification Protection
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | reminder queued for 1 PM
        | appointment rescheduled to 3 PM
        | queue worker later handles old reminder
        | old reminder is SKIPPED.
        |
        */

        $staleReason =
            $this->staleReason(
                $notification
            );

        if ($staleReason !== null) {
            $this->markSkipped(
                notification:
                    $notification,

                reason:
                    $staleReason,
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SMS Master Switch
        |--------------------------------------------------------------------------
        */

        if (
            ! (bool) config(
                'sms.enabled',
                false
            )
        ) {
            $this->markSkipped(
                notification:
                    $notification,

                reason:
                    'SMS sending is disabled.',
            );

            return;
        }

        try {
            $result =
                $gateway->send(
                    number:
                        $notification->recipient,

                    message:
                        $notification->message,
                );
        } catch (SmsGatewayException $exception) {
            $this->markFailed(
                notification:
                    $notification,

                failureKind:
                    $exception->failureKind,

                safeMessage:
                    $exception->getMessage(),
            );

            report(
                $exception
            );

            return;
        } catch (Throwable $exception) {
            /*
             * Unknown errors are classified conservatively.
             * Provider delivery status may be uncertain.
             */

            $this->markFailed(
                notification:
                    $notification,

                failureKind:
                    SmsFailureKind::Uncertain,

                safeMessage:
                    'Unexpected SMS provider error; delivery outcome is uncertain.',
            );

            report(
                $exception
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Provider Accepted
        |--------------------------------------------------------------------------
        */

        SmsNotification::query()
            ->whereKey(
                $notification->id
            )
            ->update([
                'status' =>
                    SmsNotificationStatus::Accepted
                        ->value,

                'failure_kind' =>
                    null,

                'provider_message_id' =>
                    $result
                        ->providerMessageId,

                'provider_status' =>
                    $result
                        ->providerStatus,

                'accepted_at' =>
                    now(),

                'failed_at' =>
                    null,

                'last_error' =>
                    null,

                'updated_at' =>
                    now(),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Stale Notification Validation
    |--------------------------------------------------------------------------
    |
    | Check the appointment before contacting TextBee.
    |
    */

    private function staleReason(
        SmsNotification $notification
    ): ?string {
        if (
            $notification->appointment_id
            === null
        ) {
            return null;
        }

        $appointment =
            $notification
                ->appointment()
                ->first();

        if ($appointment === null) {
            return 'Appointment no longer exists.';
        }

        return match (
            $notification->type
        ) {
            SmsNotificationType::BookingReceived =>
                $appointment->status
                === AppointmentStatus::Pending
                    ? null
                    : 'Appointment status changed before the booking SMS was sent.',

            SmsNotificationType::AppointmentConfirmed =>
                $appointment->status
                === AppointmentStatus::Confirmed
                    ? null
                    : 'Appointment is no longer confirmed.',

            SmsNotificationType::AppointmentDeclined =>
                $appointment->status
                === AppointmentStatus::Declined
                    ? null
                    : 'Appointment is no longer declined.',

            SmsNotificationType::AppointmentCancelled =>
                $appointment->status
                === AppointmentStatus::Cancelled
                    ? null
                    : 'Appointment is no longer cancelled.',

            SmsNotificationType::AppointmentReminder =>
                $this->reminderStaleReason(
                    $notification,
                    $appointment
                ),

            SmsNotificationType::RescheduleApproved,
            SmsNotificationType::RescheduleRejected =>
                null,

            SmsNotificationType::SystemTest =>
                null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Appointment Reminder Validation
    |--------------------------------------------------------------------------
    |
    | Check:
    | 1. Appointment is still confirmed.
    | 2. Appointment schedule has not changed.
    | 3. Reminder is still inside the allowed time window.
    |
    */

    private function reminderStaleReason(
        SmsNotification $notification,
        \App\Models\Appointment $appointment
    ): ?string {
        if (
            $appointment->status
            !== AppointmentStatus::Confirmed
        ) {
            return 'Appointment is no longer confirmed.';
        }

        try {
            $currentKey =
                AppointmentReminderKey::for(
                    $appointment
                );
        } catch (Throwable) {
            return 'Appointment schedule is unavailable.';
        }

        if (
            $notification->dedupe_key
            !== $currentKey
        ) {
            return 'Appointment schedule changed before the reminder was sent.';
        }

        /*
        |--------------------------------------------------------------------------
        | NEW: Prevent Late Reminders
        |--------------------------------------------------------------------------
        |
        | If the worker was offline, previously queued reminders
        | must be checked against the current appointment time.
        |
        | Example:
        |
        | Appointment: 1:00 PM
        | Minimum lead: 60 minutes
        |
        | 11:30 AM -> Allowed
        | 12:05 PM -> Skipped
        |  1:05 PM -> Skipped
        |
        */

        try {
            $timezone = (string) config(
                'app.timezone',
                'Asia/Manila'
            );

            $minimumLeadMinutes = max(
                0,
                (int) config(
                    'sms.reminders.minimum_lead_minutes',
                    60
                )
            );

            $startsAt = CarbonImmutable::parse(
                $appointment->appointment_date->toDateString()
                .' '
                .$appointment->starts_at,
                $timezone
            );

            $sendBy = $startsAt->subMinutes(
                $minimumLeadMinutes
            );

            if (
                CarbonImmutable::now($timezone)->greaterThan($sendBy)
            ) {
                return 'Appointment reminder is too late to send.';
            }
        } catch (Throwable) {
            return 'Appointment schedule is unavailable.';
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Mark Notification as Skipped
    |--------------------------------------------------------------------------
    */

    private function markSkipped(
        SmsNotification $notification,
        string $reason
    ): void {
        SmsNotification::query()
            ->whereKey(
                $notification->id
            )
            ->update([
                'status' =>
                    SmsNotificationStatus::Skipped
                        ->value,

                'failure_kind' =>
                    null,

                'failed_at' =>
                    null,

                'last_error' =>
                    mb_substr(
                        $reason,
                        0,
                        1000
                    ),

                'updated_at' =>
                    now(),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark Notification as Failed
    |--------------------------------------------------------------------------
    */

    private function markFailed(
        SmsNotification $notification,
        SmsFailureKind $failureKind,
        string $safeMessage
    ): void {
        SmsNotification::query()
            ->whereKey(
                $notification->id
            )
            ->update([
                'status' =>
                    SmsNotificationStatus::Failed
                        ->value,

                'failure_kind' =>
                    $failureKind->value,

                'failed_at' =>
                    now(),

                'last_error' =>
                    mb_substr(
                        $safeMessage,
                        0,
                        1000
                    ),

                'updated_at' =>
                    now(),
            ]);
    }
}
