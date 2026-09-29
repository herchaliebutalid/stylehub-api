<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Default Laravel Inspire Command
|--------------------------------------------------------------------------
*/

Artisan::command(
    'inspire',
    function (): void {
        $this->comment(
            Inspiring::quote()
        );
    }
)->purpose(
    'Display an inspiring quote'
);

/*
|--------------------------------------------------------------------------
| StyleHub Appointment SMS Reminders
|--------------------------------------------------------------------------
|
| Run frequently.
|
| The reminder command itself determines which confirmed appointments
| are inside the allowed reminder window.
|
| Database dedupe_key protection ensures repeated scheduler runs do not
| create duplicate reminders.
|
*/

Schedule::command(
    'stylehub:sms:queue-reminders'
)
    ->everyFiveMinutes()
    ->withoutOverlapping(
        10
    );

/*
|--------------------------------------------------------------------------
| StyleHub SMS Recovery
|--------------------------------------------------------------------------
|
| Pending:
|     safe to redispatch because no worker claimed the record.
|
| Processing:
|     never blindly resend; stale records become uncertain failures.
|
*/

Schedule::command(
    'stylehub:sms:recover'
)
    ->everyTenMinutes()
    ->withoutOverlapping(
        10
    );