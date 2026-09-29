<?php

use App\Http\Controllers\Api\V1\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix(
    'v1/auth/password'
)
    ->group(
        function (): void {

            /*
            |--------------------------------------------------------------------------
            | Forgot Password
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/forgot',
                [
                    PasswordController::class,
                    'forgot',
                ]
            )
                ->middleware(
                    'throttle:password-reset-request'
                )
                ->name(
                    'api.v1.auth.password.forgot'
                );

            /*
            |--------------------------------------------------------------------------
            | Reset Password
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/reset',
                [
                    PasswordController::class,
                    'reset',
                ]
            )
                ->middleware(
                    'throttle:password-reset-verify'
                )
                ->name(
                    'api.v1.auth.password.reset'
                );

            /*
            |--------------------------------------------------------------------------
            | Change Password
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/change',
                [
                    PasswordController::class,
                    'change',
                ]
            )
                ->middleware([
                    'auth:sanctum',
                    'account.active',
                    'throttle:password-change',
                ])
                ->name(
                    'api.v1.auth.password.change'
                );
        }
    );