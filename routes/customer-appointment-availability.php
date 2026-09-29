<?php

use App\Http\Controllers\Api\V1\Customer\AppointmentAvailabilityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Appointment Availability API
|--------------------------------------------------------------------------
|
| These endpoints are only accessible by authenticated active customers.
|
| Final URLs:
|
| GET /api/v1/customer/shops/{shop}/services/{service}/available-dates
|
| GET /api/v1/customer/shops/{shop}/services/{service}/available-slots
|
*/

Route::prefix(
    'v1/customer/shops'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'role.customer',
        'throttle:120,1',
    ])
    ->group(
        function (): void {

            /*
            |--------------------------------------------------------------------------
            | Available Dates
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/{shop}/services/{service}/available-dates',
                [
                    AppointmentAvailabilityController::class,
                    'dates',
                ]
            )
                ->whereNumber(
                    'shop'
                )
                ->whereNumber(
                    'service'
                )
                ->name(
                    'api.v1.customer.shops.services.available-dates'
                );

            /*
            |--------------------------------------------------------------------------
            | Available Time Slots
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/{shop}/services/{service}/available-slots',
                [
                    AppointmentAvailabilityController::class,
                    'slots',
                ]
            )
                ->whereNumber(
                    'shop'
                )
                ->whereNumber(
                    'service'
                )
                ->name(
                    'api.v1.customer.shops.services.available-slots'
                );
        }
    );