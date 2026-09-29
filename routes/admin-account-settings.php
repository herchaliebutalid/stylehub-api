<?php

use App\Http\Controllers\Api\V1\Admin\AdminAccountController;
use Illuminate\Support\Facades\Route;

Route::prefix(
    'v1/admin/account'
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
                AdminAccountController::class,
                'show',
            ]
        )->name(
            'api.v1.admin.account.show'
        );

        Route::put(
            '/profile',
            [
                AdminAccountController::class,
                'updateProfile',
            ]
        )->name(
            'api.v1.admin.account.profile.update'
        );

        Route::post(
            '/password',
            [
                AdminAccountController::class,
                'changePassword',
            ]
        )
            ->middleware(
                'throttle:password-change'
            )
            ->name(
                'api.v1.admin.account.password.change'
            );
    });