<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Appointment Slot Interval
    |--------------------------------------------------------------------------
    |
    | Global fallback used when a shop has not saved its own booking rules.
    | Shop-specific rules may override this value.
    |
    */

    'slot_interval_minutes' => (int) env(
        'APPOINTMENT_SLOT_INTERVAL_MINUTES',
        15
    ),

    /*
    |--------------------------------------------------------------------------
    | Minimum Booking Lead Time
    |--------------------------------------------------------------------------
    |
    | Global fallback used when a shop has not saved its own booking rules.
    |
    | 0  = customer can book the next available future slot
    | 30 = customer must book at least 30 minutes in advance
    | 60 = customer must book at least 1 hour in advance
    |
    */

    'minimum_lead_time_minutes' => (int) env(
        'APPOINTMENT_MINIMUM_LEAD_TIME_MINUTES',
        0
    ),

    /*
    |--------------------------------------------------------------------------
    | Maximum Booking Window
    |--------------------------------------------------------------------------
    |
    | Global fallback used when a shop has not saved its own booking rules.
    | 60 means appointments may be created up to 60 days ahead.
    |
    */

    'booking_window_days' => (int) env(
        'APPOINTMENT_BOOKING_WINDOW_DAYS',
        60
    ),

    /*
    |--------------------------------------------------------------------------
    | Customer Cancellation Cutoff
    |--------------------------------------------------------------------------
    |
    | Global fallback in minutes before the scheduled appointment start.
    | A value of 0 preserves the current behavior: cancellation is allowed
    | until the appointment begins.
    |
    */

    'cancellation_cutoff_minutes' => (int) env(
        'APPOINTMENT_CANCELLATION_CUTOFF_MINUTES',
        0
    ),

    /*
    |--------------------------------------------------------------------------
    | Customer Reschedule Cutoff
    |--------------------------------------------------------------------------
    |
    | Global fallback in minutes before the scheduled appointment start.
    | A value of 0 preserves the current behavior: a reschedule may be
    | requested until the appointment begins.
    |
    */

    'reschedule_cutoff_minutes' => (int) env(
        'APPOINTMENT_RESCHEDULE_CUTOFF_MINUTES',
        0
    ),

    /*
    |--------------------------------------------------------------------------
    | Availability Preview Days
    |--------------------------------------------------------------------------
    |
    | Number of upcoming dates returned to the mobile application by default.
    | This remains an application-level performance/display setting rather
    | than a shop-controlled booking rule.
    |
    */

    'availability_preview_days' => (int) env(
        'APPOINTMENT_AVAILABILITY_PREVIEW_DAYS',
        30
    ),

];