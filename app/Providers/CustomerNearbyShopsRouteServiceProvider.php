<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CustomerNearbyShopsRouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the customer nearby-shop API routes.
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
                    'routes/customer-nearby-shops.php'
                )
            );
    }
}