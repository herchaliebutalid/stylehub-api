<?php

return [

    /*
    |--------------------------------------------------------------------------
    | StyleHub SMS Master Switch
    |--------------------------------------------------------------------------
    |
    | Set SMS_ENABLED=true in your .env or Railway variables
    | to enable normal appointment-related SMS notifications.
    |
    | These include:
    | - Booking received
    | - Appointment confirmed
    | - Appointment declined
    | - Appointment cancelled
    | - Reschedule approved
    | - Reschedule rejected
    | - Appointment reminder
    |
    | Registration verification and password recovery OTPs
    | use the TextBee gateway directly.
    |
    | Keep the default false to prevent accidental SMS sending
    | when SMS_ENABLED has not been configured.
    |
    */

    'enabled' => env('SMS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Controlled Live SMS Testing
    |--------------------------------------------------------------------------
    |
    | Allows testing TextBee independently from normal
    | appointment notifications.
    |
    */

    'live_test' => [

        'enabled' => env('SMS_LIVE_TEST_ENABLED', false),

        'cooldown_minutes' => (int) env(
            'SMS_LIVE_TEST_COOLDOWN_MINUTES',
            10
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Provider
    |--------------------------------------------------------------------------
    */

    'provider' => 'textbee',

    /*
    |--------------------------------------------------------------------------
    | TextBee SMS Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | TextBee uses your registered Android phone as an
    | SMS gateway.
    |
    | The Laravel backend sends an API request to TextBee.
    | TextBee forwards the request to your registered phone.
    | Your phone sends the actual SMS through its SIM.
    |
    */

    'textbee' => [

        /*
        |--------------------------------------------------------------------------
        | TextBee API Endpoint
        |--------------------------------------------------------------------------
        */

        'endpoint' => env(
            'TEXTBEE_ENDPOINT',
            'https://api.textbee.dev/api/v1/gateway/send-sms'
        ),

        /*
        |--------------------------------------------------------------------------
        | TextBee API Key
        |--------------------------------------------------------------------------
        |
        | Store this in the backend environment only.
        | Never expose it in React Native or Expo.
        |
        */

        'api_key' => env('TEXTBEE_API_KEY'),

        /*
        |--------------------------------------------------------------------------
        | Registered Android Device ID
        |--------------------------------------------------------------------------
        |
        | This identifies the Android phone registered
        | in your TextBee account.
        |
        */

        'device_id' => env('TEXTBEE_DEVICE_ID'),

        /*
        |--------------------------------------------------------------------------
        | Connection Timeout
        |--------------------------------------------------------------------------
        */

        'connect_timeout' => (int) env(
            'TEXTBEE_CONNECT_TIMEOUT',
            5
        ),

        /*
        |--------------------------------------------------------------------------
        | Request Timeout
        |--------------------------------------------------------------------------
        */

        'timeout' => (int) env(
            'TEXTBEE_TIMEOUT',
            15
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Appointment Reminders
    |--------------------------------------------------------------------------
    |
    | Default reminder time:
    | 1440 minutes = 24 hours before the appointment.
    |
    | Minimum remaining time:
    | 60 minutes = 1 hour.
    |
    | Reminders are sent only for eligible confirmed
    | appointments when the Laravel scheduler is running.
    |
    */

    'reminders' => [

        'lead_minutes' => (int) env(
            'SMS_REMINDER_LEAD_MINUTES',
            1440
        ),

        'minimum_lead_minutes' => (int) env(
            'SMS_REMINDER_MINIMUM_LEAD_MINUTES',
            60
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Queue Recovery
    |--------------------------------------------------------------------------
    |
    | Helps recover pending SMS notifications when
    | a queue worker is interrupted.
    |
    | Processing messages with uncertain delivery
    | must not be automatically resent.
    |
    */

    'recovery' => [

        'pending_after_minutes' => (int) env(
            'SMS_PENDING_RECOVERY_MINUTES',
            5
        ),

        'processing_after_minutes' => (int) env(
            'SMS_PROCESSING_RECOVERY_MINUTES',
            10
        ),

    ],

];