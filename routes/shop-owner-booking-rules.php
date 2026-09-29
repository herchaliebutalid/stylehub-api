<?php

use App\Http\Controllers\Api\V1\ShopOwner\ShopBookingRulesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop Owner Booking Rules API
|--------------------------------------------------------------------------
|
| Booking-rule ownership is always derived from the authenticated,
| approved Shop Owner's shop.
|
*/

Route::prefix(
    'v1/shop-owner/booking-rules'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'shop-owner.approved',
    ])
    ->group(function (): void {
        Route::get(
            '/',
            [
                ShopBookingRulesController::class,
                'show',
            ]
        )
            ->middleware(
                'throttle:120,1'
            )
            ->name(
                'api.v1.shop-owner.booking-rules.show'
            );

        Route::put(
            '/',
            [
                ShopBookingRulesController::class,
                'update',
            ]
        )
            ->middleware(
                'throttle:30,1'
            )
            ->name(
                'api.v1.shop-owner.booking-rules.update'
            );
    });