<?php

use App\Http\Controllers\Api\V1\Admin\AdminAppointmentController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminFeedbackController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\AdminShopController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\ShopReviewController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\RoleLoginController;
use App\Http\Controllers\Api\V1\Customer\AppointmentController as CustomerAppointmentController;
use App\Http\Controllers\Api\V1\Customer\FeedbackController;
use App\Http\Controllers\Api\V1\Customer\PromoDiscoveryController;
use App\Http\Controllers\Api\V1\Customer\RescheduleRequestController as CustomerRescheduleRequestController;
use App\Http\Controllers\Api\V1\Customer\ShopDiscoveryController;
use App\Http\Controllers\Api\V1\Customer\ShopReviewDiscoveryController;
use App\Http\Controllers\Api\V1\ShopOwner\AppointmentController as ShopOwnerAppointmentController;
use App\Http\Controllers\Api\V1\ShopOwner\PromoController;
use App\Http\Controllers\Api\V1\ShopOwner\RescheduleRequestController as ShopOwnerRescheduleRequestController;
use App\Http\Controllers\Api\V1\ShopOwner\ServiceController;
use App\Http\Controllers\Api\V1\ShopOwner\ShopScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Mobile Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function (): void {
        Route::post(
            '/customers/register',
            [
                AuthController::class,
                'registerCustomer',
            ]
        )
            ->middleware(
                'throttle:auth-register'
            )
            ->name(
                'api.v1.auth.customers.register'
            );

        Route::post(
            '/customers/login',
            [
                RoleLoginController::class,
                'customer',
            ]
        )
            ->middleware(
                'throttle:auth-login'
            )
            ->name(
                'api.v1.auth.customers.login'
            );

        Route::post(
            '/shop-owners/register',
            [
                AuthController::class,
                'registerShopOwner',
            ]
        )
            ->middleware(
                'throttle:auth-register'
            )
            ->name(
                'api.v1.auth.shop-owners.register'
            );

        Route::post(
            '/shop-owners/login',
            [
                RoleLoginController::class,
                'shopOwner',
            ]
        )
            ->middleware(
                'throttle:auth-login'
            )
            ->name(
                'api.v1.auth.shop-owners.login'
            );

        Route::middleware([
            'auth:sanctum',
            'account.active',
        ])->group(function (): void {
            Route::get(
                '/me',
                [
                    AuthController::class,
                    'me',
                ]
            )->name(
                'api.v1.auth.me'
            );

            Route::post(
                '/logout',
                [
                    AuthController::class,
                    'logout',
                ]
            )->name(
                'api.v1.auth.logout'
            );
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Administrator Web Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/auth')
        ->group(function (): void {
            Route::post(
                '/login',
                [
                    RoleLoginController::class,
                    'admin',
                ]
            )
                ->middleware(
                    'throttle:auth-login'
                )
                ->name(
                    'api.v1.admin.auth.login'
                );

            Route::middleware([
                'auth:sanctum',
                'account.active',
                'role.admin',
            ])->group(function (): void {
                Route::get(
                    '/me',
                    [
                        AuthController::class,
                        'me',
                    ]
                )->name(
                    'api.v1.admin.auth.me'
                );

                Route::post(
                    '/logout',
                    [
                        AuthController::class,
                        'logout',
                    ]
                )->name(
                    'api.v1.admin.auth.logout'
                );
            });
        });

    /*
    |--------------------------------------------------------------------------
    | Customer Mobile API
    |--------------------------------------------------------------------------
    */

    Route::prefix('customer')
        ->middleware([
            'auth:sanctum',
            'account.active',
            'role.customer',
        ])
        ->group(function (): void {
            Route::prefix('shops')
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            ShopDiscoveryController::class,
                            'index',
                        ]
                    )
                        ->middleware(
                            'throttle:120,1'
                        )
                        ->name(
                            'api.v1.customer.shops.index'
                        );

                    Route::get(
                        '/{shop}/reviews',
                        [
                            ShopReviewDiscoveryController::class,
                            'index',
                        ]
                    )
                        ->whereNumber(
                            'shop'
                        )
                        ->middleware(
                            'throttle:120,1'
                        )
                        ->name(
                            'api.v1.customer.shops.reviews.index'
                        );

                    Route::get(
                        '/{shop}',
                        [
                            ShopDiscoveryController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'shop'
                        )
                        ->middleware(
                            'throttle:120,1'
                        )
                        ->name(
                            'api.v1.customer.shops.show'
                        );
                });

            Route::prefix('promos')
                ->middleware(
                    'throttle:120,1'
                )
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            PromoDiscoveryController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.customer.promos.index'
                    );

                    Route::get(
                        '/{promo}',
                        [
                            PromoDiscoveryController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'promo'
                        )
                        ->name(
                            'api.v1.customer.promos.show'
                        );
                });

            Route::prefix('appointments')
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            CustomerAppointmentController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.customer.appointments.index'
                    );

                    Route::post(
                        '/',
                        [
                            CustomerAppointmentController::class,
                            'store',
                        ]
                    )->name(
                        'api.v1.customer.appointments.store'
                    );

                    Route::get(
                        '/{appointment}',
                        [
                            CustomerAppointmentController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->name(
                            'api.v1.customer.appointments.show'
                        );

                    Route::patch(
                        '/{appointment}/cancel',
                        [
                            CustomerAppointmentController::class,
                            'cancel',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->name(
                            'api.v1.customer.appointments.cancel'
                        );

                    Route::post(
                        '/{appointment}/reschedule-requests',
                        [
                            CustomerAppointmentController::class,
                            'requestReschedule',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->name(
                            'api.v1.customer.appointments.reschedule-requests.store'
                        );

                    Route::post(
                        '/{appointment}/feedback',
                        [
                            FeedbackController::class,
                            'store',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->middleware(
                            'throttle:20,1'
                        )
                        ->name(
                            'api.v1.customer.appointments.feedback.store'
                        );
                });

            Route::prefix(
                'reschedule-requests'
            )->group(function (): void {
                Route::get(
                    '/',
                    [
                        CustomerRescheduleRequestController::class,
                        'index',
                    ]
                )->name(
                    'api.v1.customer.reschedule-requests.index'
                );

                Route::get(
                    '/{rescheduleRequest}',
                    [
                        CustomerRescheduleRequestController::class,
                        'show',
                    ]
                )
                    ->whereNumber(
                        'rescheduleRequest'
                    )
                    ->name(
                        'api.v1.customer.reschedule-requests.show'
                    );

                Route::patch(
                    '/{rescheduleRequest}/withdraw',
                    [
                        CustomerRescheduleRequestController::class,
                        'withdraw',
                    ]
                )
                    ->whereNumber(
                        'rescheduleRequest'
                    )
                    ->name(
                        'api.v1.customer.reschedule-requests.withdraw'
                    );
            });

            Route::prefix('feedback')
                ->middleware(
                    'throttle:60,1'
                )
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            FeedbackController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.customer.feedback.index'
                    );

                    Route::get(
                        '/{feedback}',
                        [
                            FeedbackController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'feedback'
                        )
                        ->name(
                            'api.v1.customer.feedback.show'
                        );
                });
        });

    /*
    |--------------------------------------------------------------------------
    | Shop Owner Mobile API
    |--------------------------------------------------------------------------
    */

    Route::prefix('shop-owner')
        ->middleware([
            'auth:sanctum',
            'account.active',
            'shop-owner.approved',
        ])
        ->group(function (): void {
            Route::prefix('services')
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            ServiceController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.shop-owner.services.index'
                    );

                    Route::post(
                        '/',
                        [
                            ServiceController::class,
                            'store',
                        ]
                    )->name(
                        'api.v1.shop-owner.services.store'
                    );

                    Route::get(
                        '/{service}',
                        [
                            ServiceController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'service'
                        )
                        ->name(
                            'api.v1.shop-owner.services.show'
                        );

                    Route::put(
                        '/{service}',
                        [
                            ServiceController::class,
                            'update',
                        ]
                    )
                        ->whereNumber(
                            'service'
                        )
                        ->name(
                            'api.v1.shop-owner.services.update'
                        );

                    Route::delete(
                        '/{service}',
                        [
                            ServiceController::class,
                            'destroy',
                        ]
                    )
                        ->whereNumber(
                            'service'
                        )
                        ->name(
                            'api.v1.shop-owner.services.destroy'
                        );
                });

            Route::prefix('promos')
                ->middleware(
                    'throttle:60,1'
                )
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            PromoController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.shop-owner.promos.index'
                    );

                    Route::post(
                        '/',
                        [
                            PromoController::class,
                            'store',
                        ]
                    )->name(
                        'api.v1.shop-owner.promos.store'
                    );

                    Route::get(
                        '/{promo}',
                        [
                            PromoController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'promo'
                        )
                        ->name(
                            'api.v1.shop-owner.promos.show'
                        );

                    Route::put(
                        '/{promo}',
                        [
                            PromoController::class,
                            'update',
                        ]
                    )
                        ->whereNumber(
                            'promo'
                        )
                        ->name(
                            'api.v1.shop-owner.promos.update'
                        );

                    Route::delete(
                        '/{promo}',
                        [
                            PromoController::class,
                            'destroy',
                        ]
                    )
                        ->whereNumber(
                            'promo'
                        )
                        ->name(
                            'api.v1.shop-owner.promos.destroy'
                        );
                });

            Route::get(
                '/schedule',
                [
                    ShopScheduleController::class,
                    'show',
                ]
            )->name(
                'api.v1.shop-owner.schedule.show'
            );

            Route::put(
                '/schedule',
                [
                    ShopScheduleController::class,
                    'update',
                ]
            )->name(
                'api.v1.shop-owner.schedule.update'
            );

            Route::patch(
                '/availability',
                [
                    ShopScheduleController::class,
                    'updateAvailability',
                ]
            )->name(
                'api.v1.shop-owner.availability.update'
            );

            Route::prefix('appointments')
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            ShopOwnerAppointmentController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.shop-owner.appointments.index'
                    );

                    Route::get(
                        '/{appointment}',
                        [
                            ShopOwnerAppointmentController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->name(
                            'api.v1.shop-owner.appointments.show'
                        );

                    Route::patch(
                        '/{appointment}/confirm',
                        [
                            ShopOwnerAppointmentController::class,
                            'confirm',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->name(
                            'api.v1.shop-owner.appointments.confirm'
                        );

                    Route::patch(
                        '/{appointment}/decline',
                        [
                            ShopOwnerAppointmentController::class,
                            'decline',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->name(
                            'api.v1.shop-owner.appointments.decline'
                        );

                    Route::patch(
                        '/{appointment}/cancel',
                        [
                            ShopOwnerAppointmentController::class,
                            'cancel',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->name(
                            'api.v1.shop-owner.appointments.cancel'
                        );

                    Route::patch(
                        '/{appointment}/complete',
                        [
                            ShopOwnerAppointmentController::class,
                            'complete',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->name(
                            'api.v1.shop-owner.appointments.complete'
                        );
                });

            Route::prefix(
                'reschedule-requests'
            )->group(function (): void {
                Route::get(
                    '/',
                    [
                        ShopOwnerRescheduleRequestController::class,
                        'index',
                    ]
                )->name(
                    'api.v1.shop-owner.reschedule-requests.index'
                );

                Route::get(
                    '/{rescheduleRequest}',
                    [
                        ShopOwnerRescheduleRequestController::class,
                        'show',
                    ]
                )
                    ->whereNumber(
                        'rescheduleRequest'
                    )
                    ->name(
                        'api.v1.shop-owner.reschedule-requests.show'
                    );

                Route::patch(
                    '/{rescheduleRequest}/approve',
                    [
                        ShopOwnerRescheduleRequestController::class,
                        'approve',
                    ]
                )
                    ->whereNumber(
                        'rescheduleRequest'
                    )
                    ->name(
                        'api.v1.shop-owner.reschedule-requests.approve'
                    );

                Route::patch(
                    '/{rescheduleRequest}/reject',
                    [
                        ShopOwnerRescheduleRequestController::class,
                        'reject',
                    ]
                )
                    ->whereNumber(
                        'rescheduleRequest'
                    )
                    ->name(
                        'api.v1.shop-owner.reschedule-requests.reject'
                    );
            });
        });

    /*
    |--------------------------------------------------------------------------
    | Administrator Web API
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->middleware([
            'auth:sanctum',
            'account.active',
            'role.admin',
        ])
        ->group(function (): void {
            /*
            |--------------------------------------------------------------------------
            | Dashboard
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/dashboard',
                [
                    AdminDashboardController::class,
                    'index',
                ]
            )
                ->middleware(
                    'throttle:60,1'
                )
                ->name(
                    'api.v1.admin.dashboard'
                );

            /*
            |--------------------------------------------------------------------------
            | Reports & Analytics
            |--------------------------------------------------------------------------
            |
            | Read-only Administrator reporting.
            |
            */

            Route::prefix('reports')
                ->middleware(
                    'throttle:30,1'
                )
                ->group(function (): void {
                    Route::get(
                        '/overview',
                        [
                            AdminReportController::class,
                            'overview',
                        ]
                    )->name(
                        'api.v1.admin.reports.overview'
                    );
                });

            /*
            |--------------------------------------------------------------------------
            | Appointment Monitoring
            |--------------------------------------------------------------------------
            */

            Route::prefix('appointments')
                ->middleware(
                    'throttle:60,1'
                )
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            AdminAppointmentController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.admin.appointments.index'
                    );

                    Route::get(
                        '/{appointment}',
                        [
                            AdminAppointmentController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'appointment'
                        )
                        ->name(
                            'api.v1.admin.appointments.show'
                        );
                });

            /*
            |--------------------------------------------------------------------------
            | Feedback Monitoring
            |--------------------------------------------------------------------------
            */

            Route::prefix('feedback')
                ->middleware(
                    'throttle:60,1'
                )
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            AdminFeedbackController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.admin.feedback.index'
                    );

                    Route::get(
                        '/{feedback}',
                        [
                            AdminFeedbackController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'feedback'
                        )
                        ->name(
                            'api.v1.admin.feedback.show'
                        );
                });

            /*
            |--------------------------------------------------------------------------
            | User Management
            |--------------------------------------------------------------------------
            */

            Route::prefix('users')
                ->middleware(
                    'throttle:60,1'
                )
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            AdminUserController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.admin.users.index'
                    );

                    Route::get(
                        '/{user}',
                        [
                            AdminUserController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'user'
                        )
                        ->name(
                            'api.v1.admin.users.show'
                        );

                    Route::patch(
                        '/{user}/suspend',
                        [
                            AdminUserController::class,
                            'suspend',
                        ]
                    )
                        ->whereNumber(
                            'user'
                        )
                        ->name(
                            'api.v1.admin.users.suspend'
                        );

                    Route::patch(
                        '/{user}/reactivate',
                        [
                            AdminUserController::class,
                            'reactivate',
                        ]
                    )
                        ->whereNumber(
                            'user'
                        )
                        ->name(
                            'api.v1.admin.users.reactivate'
                        );
                });

            /*
            |--------------------------------------------------------------------------
            | Shop Management
            |--------------------------------------------------------------------------
            */

            Route::prefix('shops')
                ->middleware(
                    'throttle:60,1'
                )
                ->group(function (): void {
                    Route::get(
                        '/pending',
                        [
                            ShopReviewController::class,
                            'pending',
                        ]
                    )->name(
                        'api.v1.admin.shops.pending'
                    );

                    Route::get(
                        '/',
                        [
                            AdminShopController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.admin.shops.index'
                    );

                    Route::get(
                        '/{shop}',
                        [
                            AdminShopController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'shop'
                        )
                        ->name(
                            'api.v1.admin.shops.show'
                        );

                    Route::post(
                        '/{shop}/approve',
                        [
                            ShopReviewController::class,
                            'approve',
                        ]
                    )
                        ->whereNumber(
                            'shop'
                        )
                        ->name(
                            'api.v1.admin.shops.approve'
                        );

                    Route::post(
                        '/{shop}/reject',
                        [
                            ShopReviewController::class,
                            'reject',
                        ]
                    )
                        ->whereNumber(
                            'shop'
                        )
                        ->name(
                            'api.v1.admin.shops.reject'
                        );
                });

            /*
            |--------------------------------------------------------------------------
            | Audit Logs
            |--------------------------------------------------------------------------
            */

            Route::prefix('audit-logs')
                ->middleware(
                    'throttle:60,1'
                )
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            AuditLogController::class,
                            'index',
                        ]
                    )->name(
                        'api.v1.admin.audit-logs.index'
                    );

                    Route::get(
                        '/{auditLog}',
                        [
                            AuditLogController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'auditLog'
                        )
                        ->name(
                            'api.v1.admin.audit-logs.show'
                        );
                });
        });
});