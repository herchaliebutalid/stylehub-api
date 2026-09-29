<?php

return [
    App\Providers\AppServiceProvider::class,

    App\Providers\AuditServiceProvider::class,

    App\Providers\CustomerProfileRouteServiceProvider::class,

    App\Providers\ShopOwnerLocationRouteServiceProvider::class,

    App\Providers\CustomerNearbyShopsRouteServiceProvider::class,

    App\Providers\InAppNotificationRouteServiceProvider::class,

    App\Providers\ShopOwnerMediaRouteServiceProvider::class,

    App\Providers\ShopOwnerProfileRouteServiceProvider::class,

    App\Providers\ShopOwnerBlockedDatesRouteServiceProvider::class,

    App\Providers\ShopBlockedDateProtectionServiceProvider::class,

    App\Providers\ShopOwnerBreakPeriodsRouteServiceProvider::class,

    App\Providers\ShopBreakPeriodProtectionServiceProvider::class,

    App\Providers\ShopOwnerCalendarRouteServiceProvider::class,

    App\Providers\ShopOwnerAppointmentNoShowRouteServiceProvider::class,

    App\Providers\ShopOwnerBookingRulesRouteServiceProvider::class,

    App\Providers\ShopBookingRuleEnforcementServiceProvider::class,

    App\Providers\ShopOwnerAnalyticsRouteServiceProvider::class,

    App\Providers\AdminShopSuspensionRouteServiceProvider::class,

    App\Providers\AdminSmsMonitoringRouteServiceProvider::class,

    App\Providers\AdminAccountSettingsRouteServiceProvider::class,

    App\Providers\AdminSystemSettingsRouteServiceProvider::class,
];