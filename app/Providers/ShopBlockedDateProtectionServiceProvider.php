<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Observers\AppointmentBlockedDateObserver;
use App\Observers\RescheduleRequestBlockedDateObserver;
use Illuminate\Support\ServiceProvider;

class ShopBlockedDateProtectionServiceProvider extends ServiceProvider
{
    /**
     * Register blocked-date protection observers.
     */
    public function boot(): void
    {
        Appointment::observe(
            AppointmentBlockedDateObserver::class
        );

        RescheduleRequest::observe(
            RescheduleRequestBlockedDateObserver::class
        );
    }
}