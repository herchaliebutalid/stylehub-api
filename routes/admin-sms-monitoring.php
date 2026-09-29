<?php

use App\Http\Controllers\Api\V1\Admin\AdminSmsNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix(
    'v1/admin/sms-notifications'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'role.admin',
        'throttle:60,1',
    ])
    ->group(function (): void {
        Route::get(
            '/summary',
            [
                AdminSmsNotificationController::class,
                'summary',
            ]
        )->name(
            'api.v1.admin.sms-notifications.summary'
        );

        Route::get(
            '/',
            [
                AdminSmsNotificationController::class,
                'index',
            ]
        )->name(
            'api.v1.admin.sms-notifications.index'
        );

        Route::post(
            '/{smsNotification}/retry',
            [
                AdminSmsNotificationController::class,
                'retry',
            ]
        )
            ->whereNumber(
                'smsNotification'
            )
            ->name(
                'api.v1.admin.sms-notifications.retry'
            );

        Route::get(
            '/{smsNotification}',
            [
                AdminSmsNotificationController::class,
                'show',
            ]
        )
            ->whereNumber(
                'smsNotification'
            )
            ->name(
                'api.v1.admin.sms-notifications.show'
            );
    });