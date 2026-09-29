<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShopOwnerBlockedDatesRouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the Shop Owner blocked-date routes.
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
                    'routes/shop-owner-blocked-dates.php'
                )
            );
    }
}