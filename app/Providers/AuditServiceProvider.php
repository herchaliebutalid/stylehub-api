<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Promo;
use App\Models\RescheduleRequest;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopSchedule;
use App\Observers\Audit\AppointmentAuditObserver;
use App\Observers\Audit\FeedbackAuditObserver;
use App\Observers\Audit\PromoAuditObserver;
use App\Observers\Audit\RescheduleRequestAuditObserver;
use App\Observers\Audit\ServiceAuditObserver;
use App\Observers\Audit\ShopAuditObserver;
use App\Observers\Audit\ShopScheduleAuditObserver;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Shop::observe(
            ShopAuditObserver::class
        );

        Service::observe(
            ServiceAuditObserver::class
        );

        ShopSchedule::observe(
            ShopScheduleAuditObserver::class
        );

        Promo::observe(
            PromoAuditObserver::class
        );

        Appointment::observe(
            AppointmentAuditObserver::class
        );

        RescheduleRequest::observe(
            RescheduleRequestAuditObserver::class
        );

        Feedback::observe(
            FeedbackAuditObserver::class
        );
    }
}