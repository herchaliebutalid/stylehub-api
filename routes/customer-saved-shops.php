<?php

use App\Http\Controllers\Api\V1\Customer\SavedShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Saved Shops API
|--------------------------------------------------------------------------
|
| These routes are registered under the /api prefix by bootstrap/app.php.
|
| Final URLs:
|
| GET     /api/v1/customer/saved-shops
| GET     /api/v1/customer/saved-shops/{shop}/status
| POST    /api/v1/customer/saved-shops/{shop}
| DELETE  /api/v1/customer/saved-shops/{shop}
|
*/

Route::prefix('v1/customer')
    ->middleware([
        'auth:sanctum',
        'account.active',
        'role.customer',
    ])
    ->group(function (): void {
        Route::get(
            '/saved-shops',
            [
                SavedShopController::class,
                'index',
            ]
        )
            ->middleware(
                'throttle:120,1'
            )
            ->name(
                'api.v1.customer.saved-shops.index'
            );

        Route::get(
            '/saved-shops/{shop}/status',
            [
                SavedShopController::class,
                'status',
            ]
        )
            ->whereNumber(
                'shop'
            )
            ->middleware(
                'throttle:120,1'
            )
            ->name(
                'api.v1.customer.saved-shops.status'
            );

        Route::post(
            '/saved-shops/{shop}',
            [
                SavedShopController::class,
                'store',
            ]
        )
            ->whereNumber(
                'shop'
            )
            ->middleware(
                'throttle:60,1'
            )
            ->name(
                'api.v1.customer.saved-shops.store'
            );

        Route::delete(
            '/saved-shops/{shop}',
            [
                SavedShopController::class,
                'destroy',
            ]
        )
            ->whereNumber(
                'shop'
            )
            ->middleware(
                'throttle:60,1'
            )
            ->name(
                'api.v1.customer.saved-shops.destroy'
            );
    });