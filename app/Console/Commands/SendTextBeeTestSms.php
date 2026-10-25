<?php

namespace App\Console\Commands;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Exceptions\Sms\SmsGatewayException;
use App\Models\SmsNotification;
use App\Services\Sms\TextBeeSmsGateway;
use App\Support\Sms\PhilippineSmsRecipient;
use Illuminate\Console\Command;
use Throwable;

class SendTextBeeTestSms extends Command
{
    protected $signature =
        'stylehub:sms:test
        {--number= : Internal/test recipient override. Omit this during normal manual use.}
        {--yes : Skip interactive confirmation. Intended for automated tests only.}';

    protected $description =
        'Send exactly one controlled real SMS through TextBee for StyleHub verification.';

    public function handle(
        TextBeeSmsGateway $gateway
    ): int {
        /*
        |--------------------------------------------------------------------------
        | Safety Gate #1
        |--------------------------------------------------------------------------
        |
        | Normal StyleHub SMS must remain disabled while performing
        | this isolated real-world SMS verification.
        |
        */

        if (
            (bool) config(
                'sms.enabled',
                false
            )
        ) {
            $this->error(
                'SMS_ENABLED must remain false during the controlled live test.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Safety Gate #2
        |--------------------------------------------------------------------------
        */

        if (
            ! (bool) config(
                'sms.live_test.enabled',
                false
            )
        ) {
            $this->error(
                'Controlled live SMS testing is disabled.'
            );

            $this->line(
                'Set SMS_LIVE_TEST_ENABLED=true temporarily, then clear Laravel configuration cache.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | TextBee Configuration
        |--------------------------------------------------------------------------
        */

        $apiKey =
            config(
                'sms.textbee.api_key'
            );

        if (
            ! is_string($apiKey)
            || trim($apiKey) === ''
        ) {
            $this->error(
                'TextBee API key is not configured.'
            );

            return self::FAILURE;
        }

        $deviceId =
            config(
                'sms.textbee.device_id'
            );

        if (
            ! is_string($deviceId)
            || trim($deviceId) === ''
        ) {
            $this->error(
                'TextBee Device ID is not configured.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Recipient
        |--------------------------------------------------------------------------
        |
        | During normal manual use, do not pass your phone number on the
        | command line. The secret prompt keeps it out of normal
        | PowerShell command history.
        |
        */

        $input =
            $this->option(
                'number'
            );

        if (
            ! is_string($input)
            || trim($input) === ''
        ) {
            $input =
                $this->secret(
                    'Enter your own Philippine mobile number'
                );
        }

        $recipient =
            PhilippineSmsRecipient::normalize(
                $input
            );

        if ($recipient === null) {
            $this->error(
                'Invalid Philippine mobile number.'
            );

            $this->line(
                'Accepted formats include 09171234567, 9171234567, 639171234567, and +639171234567.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Repeat Protection
        |--------------------------------------------------------------------------
        */

        $cooldownMinutes =
            max(
                1,
                (int) config(
                    'sms.live_test.cooldown_minutes',
                    10
                )
            );

        $recent =
            SmsNotification::query()
                ->where(
                    'type',
                    SmsNotificationType::SystemTest
                        ->value
                )
                ->where(
                    'recipient',
                    $recipient
                )
                ->where(
                    'created_at',
                    '>=',
                    now()->subMinutes(
                        $cooldownMinutes
                    )
                )
                ->latest(
                    'id'
                )
                ->first();

        if ($recent !== null) {
            $mustBlock =
                $recent->status
                    === SmsNotificationStatus::Accepted
                || $recent->status
                    === SmsNotificationStatus::Processing
                || (
                    $recent->status
                        === SmsNotificationStatus::Failed
                    && $recent->failure_kind
                        === SmsFailureKind::Uncertain
                );

            if ($mustBlock) {
                $this->error(
                    'A recent SMS test prevents another immediate send.'
                );

                if (
                    $recent->failure_kind
                    === SmsFailureKind::Uncertain
                ) {
                    $this->warn(
                        'The previous delivery outcome is uncertain. Sending again could create a duplicate SMS.'
                    );
                } else {
                    $this->warn(
                        "Wait {$cooldownMinutes} minutes before another controlled test."
                    );
                }

                return self::FAILURE;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Test Message
        |--------------------------------------------------------------------------
        */

        $message =
            'StyleHub SMS verification: your TextBee connection is working. No action is required.';

        $masked =
            PhilippineSmsRecipient::mask(
                $recipient
            );

        $this->newLine();

        $this->line(
            "Recipient: {$masked}"
        );

        $this->line(
            "Message: {$message}"
        );

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | Human Confirmation
        |--------------------------------------------------------------------------
        */

        if (
            ! (bool) $this->option(
                'yes'
            )
        ) {
            $confirmed =
                $this->confirm(
                    'Send exactly one real SMS now?',
                    false
                );

            if (! $confirmed) {
                $this->warn(
                    'SMS test cancelled. Nothing was sent.'
                );

                return self::SUCCESS;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Audit Record
        |--------------------------------------------------------------------------
        */

        $notification =
            new SmsNotification([
                'type' =>
                    SmsNotificationType::SystemTest,

                'recipient' =>
                    $recipient,

                'message' =>
                    $message,
            ]);

        $notification->user_id =
            null;

        $notification->appointment_id =
            null;

        $notification->status =
            SmsNotificationStatus::Processing;

        $notification->provider =
            'textbee';

        $notification->attempts =
            1;

        $notification->last_attempt_at =
            now();

        $notification->save();

        /*
        |--------------------------------------------------------------------------
        | Exactly One TextBee Request
        |--------------------------------------------------------------------------
        |
        | TextBeeSmsGateway performs no blind automatic HTTP retry.
        |
        */

        try {
            $result =
                $gateway->send(
                    number:
                        $recipient,

                    message:
                        $message,
                );
        } catch (SmsGatewayException $exception) {
            $notification->status =
                SmsNotificationStatus::Failed;

            $notification->failure_kind =
                $exception->failureKind;

            $notification->failed_at =
                now();

            $notification->last_error =
                mb_substr(
                    $exception->getMessage(),
                    0,
                    1000
                );

            $notification->save();

            $this->error(
                'TextBee test failed.'
            );

            $this->line(
                "Notification ID: {$notification->id}"
            );

            $this->line(
                'Failure classification: '
                .$exception
                    ->failureKind
                    ->label()
            );

            if (
                $exception->failureKind
                === SmsFailureKind::Uncertain
            ) {
                $this->warn(
                    'Do not immediately send another test. The provider outcome is uncertain.'
                );
            }

            return self::FAILURE;
        } catch (Throwable $exception) {
            $notification->status =
                SmsNotificationStatus::Failed;

            $notification->failure_kind =
                SmsFailureKind::Uncertain;

            $notification->failed_at =
                now();

            $notification->last_error =
                'Unexpected SMS test failure; provider outcome is uncertain.';

            $notification->save();

            report(
                $exception
            );

            $this->error(
                'Unexpected SMS test failure.'
            );

            $this->warn(
                'Do not immediately resend because the delivery outcome is uncertain.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Accepted By TextBee
        |--------------------------------------------------------------------------
        */

        $notification->status =
            SmsNotificationStatus::Accepted;

        $notification->failure_kind =
            null;

        $notification->provider_message_id =
            $result->providerMessageId;

        $notification->provider_status =
            $result->providerStatus;

        $notification->accepted_at =
            now();

        $notification->failed_at =
            null;

        $notification->last_error =
            null;

        $notification->save();

        $this->newLine();

        $this->info(
            'TextBee accepted the SMS request.'
        );

        $this->line(
            "Notification ID: {$notification->id}"
        );

        $this->line(
            'Provider message ID: '
            .(
                $notification->provider_message_id
                ?: 'Not returned'
            )
        );

        $this->line(
            'TextBee status: '
            .$notification
                ->provider_status
        );

        $this->newLine();

        $this->warn(
            'Accepted by TextBee means the request was accepted by the provider. Confirm final delivery on the receiving phone.'
        );

        return self::SUCCESS;
    }
}