<?php

use App\Http\Controllers\Api\V1\Customer\NearbyShopDiscoveryController;
use Illuminate\Support\Facades\Route;

Route::prefix(
    'v1/customer/shops'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'role.customer',
    ])
    ->group(
        function (): void {
            Route::get(
                '/nearby',
                [
                    NearbyShopDiscoveryController::class,
                    'index',
                ]
            )
                ->middleware(
                    'throttle:120,1'
                )
                ->name(
                    'api.v1.customer.shops.nearby'
                );
        }
    );