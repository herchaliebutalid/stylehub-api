<?php

use App\Http\Controllers\Api\V1\ShopOwner\ShopProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop Owner Profile API
|--------------------------------------------------------------------------
|
| These routes expose only the authenticated owner's own approved shop.
|
| Final URLs:
|
| GET /api/v1/shop-owner/shop
| PUT /api/v1/shop-owner/shop
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
            '/shop',
            [
                ShopProfileController::class,
                'show',
            ]
        )
            ->middleware(
                'throttle:120,1'
            )
            ->name(
                'api.v1.shop-owner.shop.show'
            );

        Route::put(
            '/shop',
            [
                ShopProfileController::class,
                'update',
            ]
        )
            ->middleware(
                'throttle:30,1'
            )
            ->name(
                'api.v1.shop-owner.shop.update'
            );
    });