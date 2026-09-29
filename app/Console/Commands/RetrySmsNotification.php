<?php

namespace App\Console\Commands;

use App\Enums\AuditEvent;
use App\Enums\SmsNotificationStatus;
use App\Jobs\SendSmsNotification;
use App\Models\SmsNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetrySmsNotification extends Command
{
    protected $signature =
        'stylehub:sms:retry
        {notification : SMS notification ID}';

    protected $description =
        'Retry one definitely failed SMS notification when it is safe to do so.';

    public function handle(
        AuditLogger $audit
    ): int {
        $id =
            filter_var(
                $this->argument(
                    'notification'
                ),
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' =>
                            1,
                    ],
                ]
            );

        if ($id === false) {
            $this->error(
                'A valid SMS notification ID is required.'
            );

            return self::FAILURE;
        }

        $notification =
            SmsNotification::query()
                ->find(
                    $id
                );

        if ($notification === null) {
            $this->error(
                'SMS notification not found.'
            );

            return self::FAILURE;
        }

        if (
            ! $notification
                ->isSafeToRetry()
        ) {
            $this->error(
                'This SMS notification cannot be safely retried.'
            );

            if (
                $notification
                    ->failure_kind
                    ?->value
                === 'uncertain'
            ) {
                $this->warn(
                    'The provider outcome is uncertain. Resending could create a duplicate SMS.'
                );
            }

            return self::FAILURE;
        }

        $attemptsBeforeRetry =
            $notification->attempts;

        $previousFailureKind =
            $notification
                ->failure_kind
                ?->value;

        $retryable =
            DB::transaction(
                function () use (
                    $id
                ): bool {
                    $notification =
                        SmsNotification::query()
                            ->whereKey(
                                $id
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        $notification === null
                        || ! $notification
                            ->isSafeToRetry()
                    ) {
                        return false;
                    }

                    /*
                     * Keep attempts as historical information.
                     * The next job increments it again.
                     */

                    $notification->status =
                        SmsNotificationStatus::Pending;

                    $notification->failure_kind =
                        null;

                    $notification->provider_message_id =
                        null;

                    $notification->provider_status =
                        null;

                    $notification->accepted_at =
                        null;

                    $notification->failed_at =
                        null;

                    $notification->last_error =
                        null;

                    $notification->save();

                    return true;
                }
            );

        if (! $retryable) {
            $this->error(
                'SMS notification state changed before retry could be started.'
            );

            return self::FAILURE;
        }

        $notification->refresh();

        /*
        |--------------------------------------------------------------------------
        | System Audit Event
        |--------------------------------------------------------------------------
        |
        | CLI retry has no authenticated web actor, so actor_id is null.
        |
        */

        $audit->recordSafely(
            actor:
                null,

            event:
                AuditEvent::SmsRetryQueued,

            auditable:
                $notification,

            metadata: [
                'source' =>
                    'cli',

                'attempts_before_retry' =>
                    $attemptsBeforeRetry,

                'previous_failure_kind' =>
                    $previousFailureKind,
            ],
        );

        SendSmsNotification::dispatch(
            $notification->id
        );

        $this->info(
            "SMS notification {$id} was queued for a controlled retry."
        );

        return self::SUCCESS;
    }
}