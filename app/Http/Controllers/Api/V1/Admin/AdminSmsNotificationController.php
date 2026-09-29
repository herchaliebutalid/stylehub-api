<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BrowseAdminSmsNotificationsRequest;
use App\Http\Requests\Api\V1\Admin\RetryAdminSmsNotificationRequest;
use App\Http\Resources\Api\V1\AdminSmsNotificationDetailResource;
use App\Http\Resources\Api\V1\AdminSmsNotificationResource;
use App\Models\SmsNotification;
use App\Services\Admin\AdminSmsRetryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSmsNotificationController extends Controller
{
    public function __construct(
        private readonly AdminSmsRetryService $smsRetry
    ) {}

    public function index(
        BrowseAdminSmsNotificationsRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $query =
            $this->baseQuery();

        if (isset($validated['search'])) {
            $search =
                $validated['search'];

            $query->where(
                function (
                    Builder $query
                ) use (
                    $search
                ): void {
                    $query
                        ->where(
                            'recipient',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'provider_message_id',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'last_error',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'user',
                            function (
                                Builder $userQuery
                            ) use (
                                $search
                            ): void {
                                $userQuery
                                    ->withTrashed()
                                    ->where(
                                        function (
                                            Builder $userQuery
                                        ) use (
                                            $search
                                        ): void {
                                            $userQuery
                                                ->where(
                                                    'first_name',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                                ->orWhere(
                                                    'middle_name',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                                ->orWhere(
                                                    'last_name',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                                ->orWhere(
                                                    'email',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                                ->orWhere(
                                                    'phone_number',
                                                    'like',
                                                    "%{$search}%"
                                                );
                                        }
                                    );
                            }
                        );
                }
            );
        }

        foreach (
            [
                'status',
                'type',
                'failure_kind',
                'provider',
                'user_id',
                'appointment_id',
            ] as $field
        ) {
            if (! isset($validated[$field])) {
                continue;
            }

            $query->where(
                $field,
                $validated[$field]
            );
        }

        if (isset($validated['date_from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $validated['date_from']
            );
        }

        if (isset($validated['date_to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $validated['date_to']
            );
        }

        $perPage =
            (int) (
                $validated['per_page']
                ?? 20
            );

        $notifications =
            $query
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc(
                    'id'
                )
                ->paginate(
                    $perPage
                );

        return response()->json([
            'success' =>
                true,

            'data' => [
                'sms_notifications' =>
                    AdminSmsNotificationResource::collection(
                        collect(
                            $notifications->items()
                        )
                    )->resolve(
                        $request
                    ),

                'pagination' => [
                    'current_page' =>
                        $notifications->currentPage(),

                    'per_page' =>
                        $notifications->perPage(),

                    'total' =>
                        $notifications->total(),

                    'last_page' =>
                        $notifications->lastPage(),

                    'has_more_pages' =>
                        $notifications->hasMorePages(),
                ],
            ],
        ]);
    }

    public function summary(
        Request $request
    ): JsonResponse {
        $statusCounts =
            SmsNotification::query()
                ->selectRaw(
                    'status, COUNT(*) as aggregate'
                )
                ->groupBy(
                    'status'
                )
                ->pluck(
                    'aggregate',
                    'status'
                );

        $status =
            function (
                SmsNotificationStatus $value
            ) use (
                $statusCounts
            ): int {
                return (int) (
                    $statusCounts[
                        $value->value
                    ]
                    ?? 0
                );
            };

        $safeToRetry =
            SmsNotification::query()
                ->where(
                    'status',
                    SmsNotificationStatus::Failed
                        ->value
                )
                ->where(
                    'failure_kind',
                    SmsFailureKind::Definite
                        ->value
                )
                ->count();

        $uncertainFailures =
            SmsNotification::query()
                ->where(
                    'status',
                    SmsNotificationStatus::Failed
                        ->value
                )
                ->where(
                    'failure_kind',
                    SmsFailureKind::Uncertain
                        ->value
                )
                ->count();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'summary' => [
                    'total' =>
                        (int) $statusCounts->sum(),

                    'pending' =>
                        $status(
                            SmsNotificationStatus::Pending
                        ),

                    'processing' =>
                        $status(
                            SmsNotificationStatus::Processing
                        ),

                    'accepted' =>
                        $status(
                            SmsNotificationStatus::Accepted
                        ),

                    'failed' =>
                        $status(
                            SmsNotificationStatus::Failed
                        ),

                    'skipped' =>
                        $status(
                            SmsNotificationStatus::Skipped
                        ),

                    'safe_to_retry' =>
                        $safeToRetry,

                    'uncertain_failures' =>
                        $uncertainFailures,
                ],
            ],
        ]);
    }

    public function show(
        Request $request,
        string $smsNotification
    ): JsonResponse {
        $notification =
            $this
                ->baseQuery()
                ->whereKey(
                    $smsNotification
                )
                ->firstOrFail();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'sms_notification' =>
                    (
                        new AdminSmsNotificationDetailResource(
                            $notification
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    public function retry(
        RetryAdminSmsNotificationRequest $request,
        string $smsNotification
    ): JsonResponse {
        $request->validated();

        $notification =
            $this
                ->smsRetry
                ->retry(
                    (int) $smsNotification
                );

        $notification->load([
            'user' =>
                function (
                    $query
                ): void {
                    $query->withTrashed();
                },

            'appointment',

            'appointment.shop' =>
                function (
                    $query
                ): void {
                    $query->withTrashed();
                },
        ]);

        return response()->json([
            'success' =>
                true,

            'message' =>
                'SMS notification was queued for a controlled retry.',

            'data' => [
                'sms_notification' =>
                    (
                        new AdminSmsNotificationDetailResource(
                            $notification
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    private function baseQuery(): Builder
    {
        return SmsNotification::query()
            ->with([
                'user' =>
                    function (
                        $query
                    ): void {
                        $query->withTrashed();
                    },

                'appointment',

                'appointment.shop' =>
                    function (
                        $query
                    ): void {
                        $query->withTrashed();
                    },
            ]);
    }
}