<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Normal StyleHub SMS Master Switch
    |--------------------------------------------------------------------------
    |
    | Keep false during the controlled live test.
    |
    */

    'enabled' =>
        env(
            'SMS_ENABLED',
            false
        ),

    /*
    |--------------------------------------------------------------------------
    | Controlled Live Test
    |--------------------------------------------------------------------------
    |
    | Separate from SMS_ENABLED so one Semaphore verification can be
    | performed without enabling appointment/reminder notifications.
    |
    */

    'live_test' => [
        'enabled' =>
            env(
                'SMS_LIVE_TEST_ENABLED',
                false
            ),

        /*
         * Prevent accidental repeated accepted/uncertain tests.
         */
        'cooldown_minutes' =>
            (int) env(
                'SMS_LIVE_TEST_COOLDOWN_MINUTES',
                10
            ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    */

    'provider' =>
        'semaphore',

    /*
    |--------------------------------------------------------------------------
    | Semaphore
    |--------------------------------------------------------------------------
    */

    'semaphore' => [
        'endpoint' =>
            env(
                'SEMAPHORE_ENDPOINT',
                'https://api.semaphore.co/api/v4/messages'
            ),

        'api_key' =>
            env(
                'SEMAPHORE_API_KEY'
            ),

        'sender_name' =>
            env(
                'SEMAPHORE_SENDER_NAME'
            ),

        'connect_timeout' =>
            (int) env(
                'SEMAPHORE_CONNECT_TIMEOUT',
                5
            ),

        'timeout' =>
            (int) env(
                'SEMAPHORE_TIMEOUT',
                15
            ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Appointment Reminder
    |--------------------------------------------------------------------------
    */

    'reminders' => [
        'lead_minutes' =>
            (int) env(
                'SMS_REMINDER_LEAD_MINUTES',
                1440
            ),

        'minimum_lead_minutes' =>
            (int) env(
                'SMS_REMINDER_MINIMUM_LEAD_MINUTES',
                60
            ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Recovery
    |--------------------------------------------------------------------------
    */

    'recovery' => [
        'pending_after_minutes' =>
            (int) env(
                'SMS_PENDING_RECOVERY_MINUTES',
                5
            ),

        'processing_after_minutes' =>
            (int) env(
                'SMS_PROCESSING_RECOVERY_MINUTES',
                10
            ),
    ],
];