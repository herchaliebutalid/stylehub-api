<?php

namespace App\Console\Commands;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Exceptions\Sms\SmsGatewayException;
use App\Models\SmsNotification;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Support\Sms\PhilippineSmsRecipient;
use Illuminate\Console\Command;
use Throwable;

class SendSemaphoreTestSms extends Command
{
    protected $signature =
        'stylehub:sms:test
        {--number= : Internal/test recipient override. Omit this during normal manual use.}
        {--yes : Skip interactive confirmation. Intended for automated tests only.}';

    protected $description =
        'Send exactly one controlled real SMS through Semaphore for StyleHub verification.';

    public function handle(
        SemaphoreSmsGateway $gateway
    ): int {
        /*
        |--------------------------------------------------------------------------
        | Safety Gate #1
        |--------------------------------------------------------------------------
        |
        | Normal application SMS MUST remain disabled while performing
        | this isolated verification.
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
        | Configuration
        |--------------------------------------------------------------------------
        */

        $apiKey =
            config(
                'sms.semaphore.api_key'
            );

        if (
            ! is_string($apiKey)
            || trim($apiKey) === ''
        ) {
            $this->error(
                'Semaphore API key is not configured.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Recipient
        |--------------------------------------------------------------------------
        |
        | For normal manual use, no number is passed on the command line.
        | The private prompt prevents the number from being stored in
        | ordinary PowerShell command history.
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
        | Message
        |--------------------------------------------------------------------------
        |
        | Do not begin the message with "TEST".
        |
        */

        $message =
            'StyleHub SMS verification: your Semaphore connection is working. No action is required.';

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
        | Create Audit Record Before Network Request
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
            'semaphore';

        $notification->attempts =
            1;

        $notification->last_attempt_at =
            now();

        $notification->save();

        /*
        |--------------------------------------------------------------------------
        | Exactly One Semaphore Request
        |--------------------------------------------------------------------------
        |
        | SemaphoreSmsGateway itself performs no blind HTTP retry.
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
                'Semaphore test failed.'
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
        | Accepted
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
            'Semaphore accepted the SMS request.'
        );

        $this->line(
            "Notification ID: {$notification->id}"
        );

        $this->line(
            'Provider message ID: '
            .$notification
                ->provider_message_id
        );

        $this->line(
            'Semaphore status: '
            .$notification
                ->provider_status
        );

        $this->newLine();

        $this->warn(
            'Accepted by Semaphore does not necessarily mean final handset delivery yet.'
        );

        return self::SUCCESS;
    }
}