<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Observers\AppointmentBreakPeriodObserver;
use App\Observers\RescheduleRequestBreakPeriodObserver;
use Illuminate\Support\ServiceProvider;

class ShopBreakPeriodProtectionServiceProvider extends ServiceProvider
{
    /**
     * Register break-period protection observers.
     */
    public function boot(): void
    {
        Appointment::observe(
            AppointmentBreakPeriodObserver::class
        );

        RescheduleRequest::observe(
            RescheduleRequestBreakPeriodObserver::class
        );
    }
}