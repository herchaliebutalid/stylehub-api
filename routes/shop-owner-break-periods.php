<?php

use App\Http\Controllers\Api\V1\ShopOwner\ShopBreakPeriodController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop Owner Break Periods API
|--------------------------------------------------------------------------
|
| These routes manage recurring weekly break windows for only the
| authenticated owner's approved shop.
|
*/

Route::prefix('v1/shop-owner/break-periods')
    ->middleware([
        'auth:sanctum',
        'account.active',
        'shop-owner.approved',
    ])
    ->group(function (): void {
        Route::get(
            '/',
            [
                ShopBreakPeriodController::class,
                'index',
            ]
        )
            ->middleware(
                'throttle:120,1'
            )
            ->name(
                'api.v1.shop-owner.break-periods.index'
            );

        Route::post(
            '/',
            [
                ShopBreakPeriodController::class,
                'store',
            ]
        )
            ->middleware(
                'throttle:30,1'
            )
            ->name(
                'api.v1.shop-owner.break-periods.store'
            );

        Route::put(
            '/{breakPeriod}',
            [
                ShopBreakPeriodController::class,
                'update',
            ]
        )
            ->whereNumber(
                'breakPeriod'
            )
            ->middleware(
                'throttle:30,1'
            )
            ->name(
                'api.v1.shop-owner.break-periods.update'
            );

        Route::delete(
            '/{breakPeriod}',
            [
                ShopBreakPeriodController::class,
                'destroy',
            ]
        )
            ->whereNumber(
                'breakPeriod'
            )
            ->middleware(
                'throttle:30,1'
            )
            ->name(
                'api.v1.shop-owner.break-periods.destroy'
            );
    });