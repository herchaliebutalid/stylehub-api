<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verification Code Length
    |--------------------------------------------------------------------------
    |
    | StyleHub uses a six-digit numeric OTP.
    |
    */

    'code_digits' =>
        6,

    /*
    |--------------------------------------------------------------------------
    | Verification Code Expiration
    |--------------------------------------------------------------------------
    |
    | A verification code is valid for five minutes by default.
    |
    */

    'expiration_minutes' =>
        (int) env(
            'PHONE_VERIFICATION_EXPIRATION_MINUTES',
            5
        ),

    /*
    |--------------------------------------------------------------------------
    | Maximum Verification Attempts
    |--------------------------------------------------------------------------
    |
    | After this many incorrect attempts, the OTP is invalidated and the user
    | must request a new one.
    |
    */

    'max_attempts' =>
        (int) env(
            'PHONE_VERIFICATION_MAX_ATTEMPTS',
            5
        ),

    /*
    |--------------------------------------------------------------------------
    | Resend Cooldown
    |--------------------------------------------------------------------------
    |
    | Prevent repeatedly sending SMS messages and wasting Semaphore credits.
    |
    */

    'resend_cooldown_seconds' =>
        (int) env(
            'PHONE_VERIFICATION_RESEND_COOLDOWN_SECONDS',
            60
        ),

];