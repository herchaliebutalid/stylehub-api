<?php

use App\Http\Controllers\Api\V1\ShopOwner\ShopOwnerCalendarController;
use Illuminate\Support\Facades\Route;

Route::prefix(
    'v1/shop-owner/calendar'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'shop-owner.approved',
        'throttle:120,1',
    ])
    ->group(
        function (): void {
            /*
            |--------------------------------------------------------------------------
            | Month Calendar
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/',
                [
                    ShopOwnerCalendarController::class,
                    'month',
                ]
            )->name(
                'api.v1.shop-owner.calendar.month'
            );

            /*
            |--------------------------------------------------------------------------
            | Selected Day Agenda
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/agenda',
                [
                    ShopOwnerCalendarController::class,
                    'agenda',
                ]
            )->name(
                'api.v1.shop-owner.calendar.agenda'
            );
        }
    );