<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShopOwnerLocationRouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the Shop Owner Location API routes.
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
                    'routes/shop-owner-location.php'
                )
            );
    }
}