<?php

use App\Http\Controllers\Api\V1\Auth\PhoneVerificationController;
use App\Http\Controllers\Api\V1\Auth\UnverifiedPhoneNumberController;
use Illuminate\Support\Facades\Route;

Route::prefix(
    'v1/auth/phone-verification'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
    ])
    ->group(
        function (): void {

            /*
            |-------------------------------------------------------------------------- 
            | Correct An Unverified Registration Phone Number
            |--------------------------------------------------------------------------
            |
            | This route does not send SMS. It changes only an unverified mobile
            | account after current-password confirmation, invalidates any old OTP,
            | and leaves the account unverified until the normal send/verify flow.
            |
            */

            Route::patch(
                '/phone-number',
                UnverifiedPhoneNumberController::class
            )
                ->middleware(
                    'throttle:3,60'
                )
                ->name(
                    'api.v1.auth.phone-verification.phone-number.update'
                );

            /*
            |--------------------------------------------------------------------------
            | Send / Resend OTP
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/send',
                [
                    PhoneVerificationController::class,
                    'send',
                ]
            )
                ->middleware(
                    'throttle:phone-verification-send'
                )
                ->name(
                    'api.v1.auth.phone-verification.send'
                );

            /*
            |--------------------------------------------------------------------------
            | Verify OTP
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/verify',
                [
                    PhoneVerificationController::class,
                    'verify',
                ]
            )
                ->middleware(
                    'throttle:phone-verification-verify'
                )
                ->name(
                    'api.v1.auth.phone-verification.verify'
                );
        }
    );