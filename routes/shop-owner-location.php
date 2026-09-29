<?php

use App\Http\Controllers\Api\V1\ShopOwner\ShopLocationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop Owner Location API
|--------------------------------------------------------------------------
|
| These routes are registered under the /api prefix by the dedicated route
| service provider.
|
| Final URLs:
|
| GET  /api/v1/shop-owner/location
| PUT  /api/v1/shop-owner/location
|
*/

Route::prefix('v1/shop-owner')
    ->middleware([
        'auth:sanctum',
        'account.active',
        'shop-owner.approved',
    ])
    ->group(function (): void {
        Route::get(
            '/location',
            [
                ShopLocationController::class,
                'show',
            ]
        )
            ->middleware(
                'throttle:120,1'
            )
            ->name(
                'api.v1.shop-owner.location.show'
            );

        Route::put(
            '/location',
            [
                ShopLocationController::class,
                'update',
            ]
        )
            ->middleware(
                'throttle:60,1'
            )
            ->name(
                'api.v1.shop-owner.location.update'
            );
    });