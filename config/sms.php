<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Normal StyleHub SMS Master Switch
    |--------------------------------------------------------------------------
    |
    | When false, normal appointment and reminder SMS notifications
    | will not be sent.
    |
    | Keep this false while we are integrating and testing TextBee.
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
    | This is separate from SMS_ENABLED.
    |
    | It allows us to test one real TextBee SMS without enabling
    | all appointment notifications and reminders at the same time.
    |
    */

    'live_test' => [

        'enabled' =>
            env(
                'SMS_LIVE_TEST_ENABLED',
                false
            ),

        /*
         * Prevent accidental repeated test messages.
         */
        'cooldown_minutes' =>
            (int) env(
                'SMS_LIVE_TEST_COOLDOWN_MINUTES',
                10
            ),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Provider
    |--------------------------------------------------------------------------
    */

    'provider' =>
        'textbee',

    /*
    |--------------------------------------------------------------------------
    | TextBee SMS Gateway
    |--------------------------------------------------------------------------
    |
    | StyleHub communicates with the TextBee REST API.
    |
    | TextBee then sends the request to our registered Android
    | gateway phone, and the phone sends the SMS through its SIM.
    |
    */

    'textbee' => [

        /*
        |--------------------------------------------------------------------------
        | API Endpoint
        |--------------------------------------------------------------------------
        */

        'endpoint' =>
            env(
                'TEXTBEE_ENDPOINT',
                'https://api.textbee.dev/api/v1/gateway/send-sms'
            ),

        /*
        |--------------------------------------------------------------------------
        | API Key
        |--------------------------------------------------------------------------
        |
        | Never put this key inside the React Native / Expo project.
        | It belongs only in the Laravel backend environment.
        |
        */

        'api_key' =>
            env(
                'TEXTBEE_API_KEY'
            ),

        /*
        |--------------------------------------------------------------------------
        | Gateway Device
        |--------------------------------------------------------------------------
        |
        | This is the Device ID shown for the registered OPPO phone
        | in the TextBee dashboard.
        |
        | Supplying the ID ensures that StyleHub uses this exact
        | Android phone as its SMS gateway.
        |
        */

        'device_id' =>
            env(
                'TEXTBEE_DEVICE_ID'
            ),

        /*
        |--------------------------------------------------------------------------
        | Connection Timeout
        |--------------------------------------------------------------------------
        */

        'connect_timeout' =>
            (int) env(
                'TEXTBEE_CONNECT_TIMEOUT',
                5
            ),

        /*
        |--------------------------------------------------------------------------
        | Request Timeout
        |--------------------------------------------------------------------------
        */

        'timeout' =>
            (int) env(
                'TEXTBEE_TIMEOUT',
                15
            ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Appointment Reminder
    |--------------------------------------------------------------------------
    |
    | 1440 minutes = 24 hours.
    |
    | StyleHub will normally prepare an appointment reminder
    | approximately 24 hours before the appointment.
    |
    */

    'reminders' => [

        'lead_minutes' =>
            (int) env(
                'SMS_REMINDER_LEAD_MINUTES',
                1440
            ),

        /*
         * Avoid creating a reminder when the appointment is already
         * too close to its scheduled time.
         *
         * 60 minutes = 1 hour.
         */
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
    |
    | These values are used by StyleHub's existing SMS recovery
    | mechanism when queued notifications become stuck.
    |
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