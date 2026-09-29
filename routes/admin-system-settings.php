<?php

use App\Http\Controllers\Api\V1\Admin\AdminSystemSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix(
    'v1/admin/system-settings'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'role.admin',
        'throttle:60,1',
    ])
    ->group(function (): void {
        Route::get(
            '/',
            [
                AdminSystemSettingsController::class,
                'index',
            ]
        )->name(
            'api.v1.admin.system-settings.index'
        );

        Route::put(
            '/sms-templates/{smsTemplateType}',
            [
                AdminSystemSettingsController::class,
                'updateSmsTemplate',
            ]
        )->name(
            'api.v1.admin.system-settings.sms-templates.update'
        );
    });