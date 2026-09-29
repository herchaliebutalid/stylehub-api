<?php

use App\Http\Controllers\Api\V1\Admin\AdminShopSuspensionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administrator Independent Shop Suspension API
|--------------------------------------------------------------------------
|
| These actions change only the Shop's administrative state. They never
| suspend/reactivate the Shop Owner's User account.
|
*/

Route::prefix(
    'v1/admin/shops'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'role.admin',
        'throttle:30,1',
    ])
    ->group(function (): void {
        Route::patch(
            '/{shop}/suspend',
            [
                AdminShopSuspensionController::class,
                'suspend',
            ]
        )
            ->whereNumber(
                'shop'
            )
            ->name(
                'api.v1.admin.shops.suspend'
            );

        Route::patch(
            '/{shop}/reactivate',
            [
                AdminShopSuspensionController::class,
                'reactivate',
            ]
        )
            ->whereNumber(
                'shop'
            )
            ->name(
                'api.v1.admin.shops.reactivate'
            );
    });