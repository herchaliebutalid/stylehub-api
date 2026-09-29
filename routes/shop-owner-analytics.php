<?php

use App\Http\Controllers\Api\V1\ShopOwner\ShopOwnerAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::prefix(
    'v1/shop-owner/analytics'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'shop-owner.approved',
        'throttle:30,1',
    ])
    ->group(function (): void {
        Route::get(
            '/',
            [
                ShopOwnerAnalyticsController::class,
                'index',
            ]
        )->name(
            'api.v1.shop-owner.analytics.index'
        );
    });