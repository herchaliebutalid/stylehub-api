<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShopOwnerCalendarRouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap Shop Owner calendar/day-agenda routes.
     */
    public function boot(): void
    {
        Route::middleware(
            'api'
        )
            ->prefix(
                'api'
            )
            ->group(
                base_path(
                    'routes/shop-owner-calendar.php'
                )
            );
    }
}