<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShopOwnerBreakPeriodsRouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the Shop Owner break-period routes.
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
                    'routes/shop-owner-break-periods.php'
                )
            );
    }
}