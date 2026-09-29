<?php

use App\Http\Controllers\Api\V1\ShopOwner\ShopBlockedDateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop Owner Blocked Dates API
|--------------------------------------------------------------------------
|
| These routes manage whole-day closures for only the authenticated owner's
| approved shop.
|
*/

Route::prefix('v1/shop-owner/blocked-dates')
    ->middleware([
        'auth:sanctum',
        'account.active',
        'shop-owner.approved',
    ])
    ->group(function (): void {
        Route::get(
            '/',
            [
                ShopBlockedDateController::class,
                'index',
            ]
        )
            ->middleware(
                'throttle:120,1'
            )
            ->name(
                'api.v1.shop-owner.blocked-dates.index'
            );

        Route::post(
            '/',
            [
                ShopBlockedDateController::class,
                'store',
            ]
        )
            ->middleware(
                'throttle:30,1'
            )
            ->name(
                'api.v1.shop-owner.blocked-dates.store'
            );

        Route::put(
            '/{blockedDate}',
            [
                ShopBlockedDateController::class,
                'update',
            ]
        )
            ->whereNumber(
                'blockedDate'
            )
            ->middleware(
                'throttle:30,1'
            )
            ->name(
                'api.v1.shop-owner.blocked-dates.update'
            );

        Route::delete(
            '/{blockedDate}',
            [
                ShopBlockedDateController::class,
                'destroy',
            ]
        )
            ->whereNumber(
                'blockedDate'
            )
            ->middleware(
                'throttle:30,1'
            )
            ->name(
                'api.v1.shop-owner.blocked-dates.destroy'
            );
    });