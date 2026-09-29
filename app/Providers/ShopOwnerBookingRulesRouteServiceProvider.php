<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShopOwnerBookingRulesRouteServiceProvider extends ServiceProvider
{
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
                    'routes/shop-owner-booking-rules.php'
                )
            );
    }
}