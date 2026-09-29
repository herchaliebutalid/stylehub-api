<?php

use App\Http\Controllers\Api\V1\ShopOwner\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::prefix(
    'v1/shop-owner/appointments'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'shop-owner.approved',
    ])
    ->group(
        function (): void {
            Route::patch(
                '/{appointment}/no-show',
                [
                    AppointmentController::class,
                    'noShow',
                ]
            )
                ->whereNumber(
                    'appointment'
                )
                ->name(
                    'api.v1.shop-owner.appointments.no-show'
                );
        }
    );