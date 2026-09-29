<?php

namespace App\Services\Admin;

use App\Enums\AuditEvent;
use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Jobs\SendSmsNotification;
use App\Models\SmsNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminSmsRetryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function retry(
        int $smsNotificationId
    ): SmsNotification {
        $result =
            DB::transaction(
                function () use (
                    $smsNotificationId
                ): array {
                    $notification =
                        SmsNotification::query()
                            ->whereKey(
                                $smsNotificationId
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($notification === null) {
                        throw (
                            new ModelNotFoundException()
                        )->setModel(
                            SmsNotification::class,
                            [
                                $smsNotificationId,
                            ]
                        );
                    }

                    if (
                        ! $notification
                            ->isSafeToRetry()
                    ) {
                        if (
                            $notification->failure_kind
                            === SmsFailureKind::Uncertain
                        ) {
                            throw new ConflictHttpException(
                                'This SMS notification has an uncertain provider outcome and cannot be retried safely.'
                            );
                        }

                        throw new ConflictHttpException(
                            'This SMS notification cannot be safely retried.'
                        );
                    }

                    $attemptsBeforeRetry =
                        $notification->attempts;

                    $previousFailureKind =
                        $notification
                            ->failure_kind
                            ?->value;

                    $previousStatus =
                        $notification
                            ->status
                            ->value;

                    /*
                    |--------------------------------------------------------------------------
                    | Prepare Controlled Retry
                    |--------------------------------------------------------------------------
                    |
                    | Keep the attempt counter as delivery history. The existing queue job
                    | increments it again only when it successfully claims this Pending row.
                    |
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

                    return [
                        'notification' =>
                            $notification,

                        'metadata' => [
                            'source' =>
                                'admin_api',

                            'attempts_before_retry' =>
                                $attemptsBeforeRetry,

                            'previous_status' =>
                                $previousStatus,

                            'new_status' =>
                                SmsNotificationStatus::Pending
                                    ->value,

                            'previous_failure_kind' =>
                                $previousFailureKind,
                        ],
                    ];
                },
                3
            );

        /** @var SmsNotification $notification */
        $notification =
            $result['notification'];

        $this
            ->auditLogger
            ->recordCurrentSafely(
                AuditEvent::SmsRetryQueued,
                $notification,
                $result['metadata']
            );

        SendSmsNotification::dispatch(
            $notification->id
        );

        return $notification;
    }
}