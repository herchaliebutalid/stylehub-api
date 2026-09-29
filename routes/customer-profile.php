<?php

use App\Http\Controllers\Api\V1\Customer\CustomerProfileController;

use App\Http\Controllers\Api\V1\Customer\ProfilePhotoController
    as CustomerProfilePhotoController;

use App\Http\Controllers\Api\V1\ShopOwner\ProfilePhotoController
    as ShopOwnerProfilePhotoController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| StyleHub Customer Profile API
|--------------------------------------------------------------------------
|
| This route file manages:
|
| - Customer personal information
| - Customer profile photo
| - Shop Owner profile photo
|
*/


/*
|--------------------------------------------------------------------------
| Customer Profile
|--------------------------------------------------------------------------
*/

Route::prefix(
    'v1/customer/profile'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'role.customer',
    ])
    ->group(
        function (): void {

            /*
            |--------------------------------------------------------------------------
            | Personal Information
            |--------------------------------------------------------------------------
            |
            | PATCH:
            | /api/v1/customer/profile
            |
            */

            Route::patch(
                '/',
                [
                    CustomerProfileController::class,
                    'update',
                ]
            )
                ->middleware(
                    'throttle:20,1'
                )
                ->name(
                    'api.v1.customer.profile.update'
                );

            /*
            |--------------------------------------------------------------------------
            | Profile Photo
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/photo',
                [
                    CustomerProfilePhotoController::class,
                    'store',
                ]
            )
                ->middleware(
                    'throttle:20,1'
                )
                ->name(
                    'api.v1.customer.profile.photo.store'
                );

            Route::delete(
                '/photo',
                [
                    CustomerProfilePhotoController::class,
                    'destroy',
                ]
            )
                ->middleware(
                    'throttle:20,1'
                )
                ->name(
                    'api.v1.customer.profile.photo.destroy'
                );
        }
    );


/*
|--------------------------------------------------------------------------
| Shop Owner Profile Photo
|--------------------------------------------------------------------------
*/

Route::prefix(
    'v1/shop-owner/profile'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'shop-owner.approved',
    ])
    ->group(
        function (): void {
            Route::post(
                '/photo',
                [
                    ShopOwnerProfilePhotoController::class,
                    'store',
                ]
            )
                ->middleware(
                    'throttle:20,1'
                )
                ->name(
                    'api.v1.shop-owner.profile.photo.store'
                );

            Route::delete(
                '/photo',
                [
                    ShopOwnerProfilePhotoController::class,
                    'destroy',
                ]
            )
                ->middleware(
                    'throttle:20,1'
                )
                ->name(
                    'api.v1.shop-owner.profile.photo.destroy'
                );
        }
    );