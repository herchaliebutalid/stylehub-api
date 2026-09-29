<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notifications\BrowseInAppNotificationsRequest;
use App\Http\Requests\Api\V1\Notifications\NotificationAccessRequest;
use App\Http\Resources\Api\V1\InAppNotificationResource;
use App\Models\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InAppNotificationController extends Controller
{
    /**
     * List notifications belonging only to the authenticated account.
     */
    public function index(
        BrowseInAppNotificationsRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $user =
            $request->user();

        $status =
            $validated['status']
            ?? 'all';

        $perPage =
            (int) (
                $validated['per_page']
                ?? 20
            );

        $query =
            $user
                ->inAppNotifications()
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc(
                    'id'
                );

        /*
        |--------------------------------------------------------------------------
        | Read State Filtering
        |--------------------------------------------------------------------------
        */

        if (
            $status === 'unread'
        ) {
            $query->whereNull(
                'read_at'
            );
        }

        if (
            $status === 'read'
        ) {
            $query->whereNotNull(
                'read_at'
            );
        }

        $notifications =
            $query->paginate(
                $perPage
            );

        $unreadCount =
            $user
                ->inAppNotifications()
                ->unread()
                ->count();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'notifications' =>
                    InAppNotificationResource::collection(
                        $notifications
                            ->getCollection()
                    )->resolve(
                        $request
                    ),

                'unread_count' =>
                    $unreadCount,

                'pagination' => [
                    'current_page' =>
                        $notifications
                            ->currentPage(),

                    'per_page' =>
                        $notifications
                            ->perPage(),

                    'total' =>
                        $notifications
                            ->total(),

                    'last_page' =>
                        $notifications
                            ->lastPage(),

                    'has_more_pages' =>
                        $notifications
                            ->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Return the authenticated account's unread notification count.
     */
    public function unreadCount(
        NotificationAccessRequest $request
    ): JsonResponse {
        $count =
            $request
                ->user()
                ->inAppNotifications()
                ->unread()
                ->count();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'unread_count' =>
                    $count,
            ],
        ]);
    }

    /**
     * Mark one owned notification as read.
     *
     * The operation is intentionally idempotent.
     */
    public function markRead(
        NotificationAccessRequest $request,
        string $notification
    ): JsonResponse {
        $user =
            $request->user();

        $wasAlreadyRead =
            false;

        $ownedNotification =
            DB::transaction(
                function () use (
                    $user,
                    $notification,
                    &$wasAlreadyRead
                ): InAppNotification {
                    $ownedNotification =
                        $user
                            ->inAppNotifications()
                            ->whereKey(
                                $notification
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $wasAlreadyRead =
                        $ownedNotification
                            ->isRead();

                    if (
                        ! $wasAlreadyRead
                    ) {
                        /*
                         * read_at is deliberately not mass assignable.
                         * This assignment is trusted server-side state.
                         */

                        $ownedNotification
                            ->read_at =
                                now();

                        $ownedNotification
                            ->save();
                    }

                    return $ownedNotification
                        ->fresh();
                }
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                $wasAlreadyRead
                    ? 'Notification is already marked as read.'
                    : 'Notification marked as read.',

            'data' => [
                'notification' =>
                    (
                        new InAppNotificationResource(
                            $ownedNotification
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    /**
     * Mark every unread notification belonging to the current account as read.
     */
    public function markAllRead(
        NotificationAccessRequest $request
    ): JsonResponse {
        $user =
            $request->user();

        $now =
            now();

        /*
        |--------------------------------------------------------------------------
        | Account-Scoped Bulk Update
        |--------------------------------------------------------------------------
        |
        | No notification belonging to another user can be modified here.
        |
        */

        $updatedCount =
            $user
                ->inAppNotifications()
                ->unread()
                ->update([
                    'read_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ]);

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Notifications marked as read.',

            'data' => [
                'marked_read_count' =>
                    $updatedCount,

                'unread_count' =>
                    0,
            ],
        ]);
    }
}