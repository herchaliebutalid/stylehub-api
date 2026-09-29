<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShopOwnerProfileRouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the Shop Owner profile routes.
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
                    'routes/shop-owner-profile.php'
                )
            );
    }
}