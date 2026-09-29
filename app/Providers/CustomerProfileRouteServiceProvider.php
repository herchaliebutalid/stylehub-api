<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CustomerProfileRouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the Customer Profile API routes.
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
                    'routes/customer-profile.php'
                )
            );
    }
}