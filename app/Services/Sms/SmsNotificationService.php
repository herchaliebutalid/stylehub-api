<?php

namespace App\Services\Sms;

use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Jobs\SendSmsNotification;
use App\Models\Appointment;
use App\Models\SmsNotification;
use App\Models\User;
use App\Support\Sms\PhilippineSmsRecipient;
use Illuminate\Database\QueryException;

class SmsNotificationService
{
    /**
     * Create an SMS notification and enqueue it.
     *
     * A dedupe key is optional and is mainly used
     * by scheduled appointment reminders.
     */
    public function queue(
        User $recipient,
        SmsNotificationType $type,
        string $message,
        ?Appointment $appointment = null,
        ?string $dedupeKey = null
    ): SmsNotification {
        /*
        |--------------------------------------------------------------------------
        | Existing Dedupe Record
        |--------------------------------------------------------------------------
        */

        if ($dedupeKey !== null) {
            $existing =
                SmsNotification::query()
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
        | Normalize Philippine Mobile Number
        |--------------------------------------------------------------------------
        */

        $phoneNumber =
            PhilippineSmsRecipient::normalize(
                $recipient->phone_number
            );

        try {
            /*
            |--------------------------------------------------------------------------
            | Missing / Invalid Number
            |--------------------------------------------------------------------------
            */

            if ($phoneNumber === null) {
                $notification =
                    new SmsNotification([
                        'type' =>
                            $type,

                        'recipient' =>
                            'unavailable',

                        'message' =>
                            trim(
                                $message
                            ),
                    ]);

                $notification->user_id =
                    $recipient->id;

                $notification->appointment_id =
                    $appointment?->id;

                $notification->dedupe_key =
                    $dedupeKey;

                $notification->status =
                    SmsNotificationStatus::Skipped;

                $notification->provider =
                    'textbee';

                $notification->last_error =
                    'Recipient Philippine mobile number is invalid or unavailable.';

                $notification->save();

                return $notification;
            }

            /*
            |--------------------------------------------------------------------------
            | Pending Notification
            |--------------------------------------------------------------------------
            */

            $notification =
                new SmsNotification([
                    'type' =>
                        $type,

                    'recipient' =>
                        $phoneNumber,

                    'message' =>
                        trim(
                            $message
                        ),
                ]);

            $notification->user_id =
                $recipient->id;

            $notification->appointment_id =
                $appointment?->id;

            $notification->dedupe_key =
                $dedupeKey;

            $notification->status =
                SmsNotificationStatus::Pending;

            /*
             * New pending notifications use TextBee
             * to match the active SMS gateway.
             */
            $notification->provider =
                'textbee';

            $notification->save();
        } catch (QueryException $exception) {
            /*
             * The unique dedupe_key remains the final defense
             * against concurrent reminder creation.
             */
            if ($dedupeKey !== null) {
                $existing =
                    SmsNotification::query()
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

        SendSmsNotification::dispatch(
            $notification->id
        );

        return $notification;
    }
}
