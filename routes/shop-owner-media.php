<?php

use App\Http\Controllers\Api\V1\ShopOwner\ServiceImageController;
use App\Http\Controllers\Api\V1\ShopOwner\ShopGalleryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop Owner Media API
|--------------------------------------------------------------------------
|
| Authentication, active-account checking, verified phone checking,
| Shop Owner role checking and approved-shop checking are handled through
| the existing shop-owner.approved middleware.
|
*/

Route::prefix(
    'v1/shop-owner'
)
    ->middleware([
        'auth:sanctum',
        'account.active',
        'shop-owner.approved',
    ])
    ->group(
        function (): void {
            /*
            |--------------------------------------------------------------------------
            | Shop Gallery
            |--------------------------------------------------------------------------
            */

            Route::prefix(
                'gallery'
            )
                ->group(
                    function (): void {
                        Route::get(
                            '/',
                            [
                                ShopGalleryController::class,
                                'index',
                            ]
                        )
                            ->middleware(
                                'throttle:120,1'
                            )
                            ->name(
                                'api.v1.shop-owner.gallery.index'
                            );

                        Route::post(
                            '/',
                            [
                                ShopGalleryController::class,
                                'store',
                            ]
                        )
                            ->middleware(
                                'throttle:20,1'
                            )
                            ->name(
                                'api.v1.shop-owner.gallery.store'
                            );

                        Route::put(
                            '/order',
                            [
                                ShopGalleryController::class,
                                'reorder',
                            ]
                        )
                            ->middleware(
                                'throttle:60,1'
                            )
                            ->name(
                                'api.v1.shop-owner.gallery.order'
                            );

                        Route::delete(
                            '/{galleryImage}',
                            [
                                ShopGalleryController::class,
                                'destroy',
                            ]
                        )
                            ->whereNumber(
                                'galleryImage'
                            )
                            ->middleware(
                                'throttle:60,1'
                            )
                            ->name(
                                'api.v1.shop-owner.gallery.destroy'
                            );
                    }
                );

            /*
            |--------------------------------------------------------------------------
            | Service Display Image
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/services/{service}/image',
                [
                    ServiceImageController::class,
                    'store',
                ]
            )
                ->whereNumber(
                    'service'
                )
                ->middleware(
                    'throttle:20,1'
                )
                ->name(
                    'api.v1.shop-owner.services.image.store'
                );

            Route::delete(
                '/services/{service}/image',
                [
                    ServiceImageController::class,
                    'destroy',
                ]
            )
                ->whereNumber(
                    'service'
                )
                ->middleware(
                    'throttle:20,1'
                )
                ->name(
                    'api.v1.shop-owner.services.image.destroy'
                );
        }
    );