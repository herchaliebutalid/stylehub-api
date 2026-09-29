<?php

namespace App\Console\Commands;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Jobs\SendSmsNotification;
use App\Models\SmsNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverSmsNotifications extends Command
{
    protected $signature =
        'stylehub:sms:recover';

    protected $description =
        'Safely recover stale pending SMS jobs and quarantine uncertain processing records.';

    public function handle(): int
    {
        $pendingAfterMinutes =
            max(
                1,
                (int) config(
                    'sms.recovery.pending_after_minutes',
                    5
                )
            );

        $processingAfterMinutes =
            max(
                1,
                (int) config(
                    'sms.recovery.processing_after_minutes',
                    10
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Stale Pending
        |--------------------------------------------------------------------------
        |
        | Pending means SendSmsNotification has not claimed the row yet.
        |
        | It is therefore safe to put another copy of the queue job into the
        | queue. If the original job is also still there, the row-lock/status
        | check means only one will actually contact Semaphore.
        |
        */

        $pendingIds =
            SmsNotification::query()
                ->where(
                    'status',
                    SmsNotificationStatus::Pending
                        ->value
                )
                ->where(
                    'created_at',
                    '<=',
                    now()->subMinutes(
                        $pendingAfterMinutes
                    )
                )
                ->limit(500)
                ->pluck(
                    'id'
                );

        foreach (
            $pendingIds as $id
        ) {
            SendSmsNotification::dispatch(
                (int) $id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Stale Processing
        |--------------------------------------------------------------------------
        |
        | Processing means a worker claimed the notification.
        |
        | If that worker vanished, we cannot know whether the HTTP call reached
        | Semaphore before the crash.
        |
        | Therefore:
        |
        | DO NOT resend.
        | Mark delivery outcome UNCERTAIN instead.
        |
        */

        $processingIds =
            SmsNotification::query()
                ->where(
                    'status',
                    SmsNotificationStatus::Processing
                        ->value
                )
                ->where(
                    function (
                        $query
                    ) use (
                        $processingAfterMinutes
                    ): void {
                        $threshold =
                            now()->subMinutes(
                                $processingAfterMinutes
                            );

                        $query
                            ->where(
                                'last_attempt_at',
                                '<=',
                                $threshold
                            )
                            ->orWhere(
                                function (
                                    $query
                                ) use (
                                    $threshold
                                ): void {
                                    $query
                                        ->whereNull(
                                            'last_attempt_at'
                                        )
                                        ->where(
                                            'created_at',
                                            '<=',
                                            $threshold
                                        );
                                }
                            );
                    }
                )
                ->limit(500)
                ->pluck(
                    'id'
                );

        $quarantined = 0;

        foreach (
            $processingIds as $id
        ) {
            $changed =
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
                            || $notification->status
                            !== SmsNotificationStatus::Processing
                        ) {
                            return false;
                        }

                        $notification->status =
                            SmsNotificationStatus::Failed;

                        $notification->failure_kind =
                            SmsFailureKind::Uncertain;

                        $notification->failed_at =
                            now();

                        $notification->last_error =
                            'SMS processing became stale; provider delivery outcome is uncertain.';

                        $notification->save();

                        return true;
                    }
                );

            if ($changed) {
                $quarantined++;
            }
        }

        $this->info(
            sprintf(
                'Redispatched %d stale pending notification(s); quarantined %d uncertain processing notification(s).',
                $pendingIds->count(),
                $quarantined,
            )
        );

        return self::SUCCESS;
    }
}