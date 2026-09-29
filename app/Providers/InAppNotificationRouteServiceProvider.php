<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InAppNotificationRouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the mobile in-app notification routes.
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
                    'routes/in-app-notifications.php'
                )
            );
    }
}