<?php

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Exceptions\Sms\SmsGatewayException;
use App\Models\SmsNotification;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Support\AppointmentReminderKey;
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
        SemaphoreSmsGateway $gateway
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
        |        ↓
        | appointment rescheduled to 3 PM
        |        ↓
        | queue worker finally handles old reminder
        |        ↓
        | SKIP old 1 PM reminder
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
             * Unknown errors are always classified conservatively.
             *
             * We do not know whether provider communication occurred.
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

    /**
     * Reject stale status/reminder messages before contacting
     * the external SMS provider.
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

            /*
             * Reschedule result notifications cannot currently be validated
             * purely from appointment status because their source is the
             * reschedule-request record itself.
             */
            SmsNotificationType::RescheduleApproved,
            SmsNotificationType::RescheduleRejected,
            SmsNotificationType::AppointmentReminder =>
                null,
        };
    }

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

        return null;
    }

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