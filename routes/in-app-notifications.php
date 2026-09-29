<?php

use App\Http\Controllers\Api\V1\Notifications\InAppNotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile In-App Notification API
|--------------------------------------------------------------------------
|
| Final endpoints:
|
| GET   /api/v1/notifications
| GET   /api/v1/notifications/unread-count
| PATCH /api/v1/notifications/{notification}/read
| PATCH /api/v1/notifications/read-all
|
| Role and phone-verification authorization is enforced by the FormRequest
| classes used by the controller.
|
*/

Route::prefix(
    'v1/notifications'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
    ])
    ->group(
        function (): void {
            /*
            |--------------------------------------------------------------------------
            | Read
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/',
                [
                    InAppNotificationController::class,
                    'index',
                ]
            )
                ->middleware(
                    'throttle:120,1'
                )
                ->name(
                    'api.v1.notifications.index'
                );

            Route::get(
                '/unread-count',
                [
                    InAppNotificationController::class,
                    'unreadCount',
                ]
            )
                ->middleware(
                    'throttle:120,1'
                )
                ->name(
                    'api.v1.notifications.unread-count'
                );

            /*
            |--------------------------------------------------------------------------
            | Write
            |--------------------------------------------------------------------------
            */

            Route::patch(
                '/read-all',
                [
                    InAppNotificationController::class,
                    'markAllRead',
                ]
            )
                ->middleware(
                    'throttle:60,1'
                )
                ->name(
                    'api.v1.notifications.read-all'
                );

            Route::patch(
                '/{notification}/read',
                [
                    InAppNotificationController::class,
                    'markRead',
                ]
            )
                ->whereNumber(
                    'notification'
                )
                ->middleware(
                    'throttle:60,1'
                )
                ->name(
                    'api.v1.notifications.read'
                );
        }
    );