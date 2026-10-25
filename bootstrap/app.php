
<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureShopOwnerHasApprovedShop;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\NormalizeApiErrorResponse;
use App\Support\Api\ApiErrorResponseNormalizer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(
    basePath:
        dirname(
            __DIR__
        )
)
    ->withRouting(
        web:
            __DIR__
            .'/../routes/web.php',

        api:
            __DIR__
            .'/../routes/api.php',

        commands:
            __DIR__
            .'/../routes/console.php',

        health:
            '/up',

        then: function (): void {

            /*
            |--------------------------------------------------------------------------
            | Customer Saved Shops
            |--------------------------------------------------------------------------
            */

            Route::middleware(
                'api'
            )
                ->prefix(
                    'api'
                )
                ->group(
                    base_path(
                        'routes/customer-saved-shops.php'
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | Customer Appointment Availability
            |--------------------------------------------------------------------------
            */

            Route::middleware(
                'api'
            )
                ->prefix(
                    'api'
                )
                ->group(
                    base_path(
                        'routes/customer-appointment-availability.php'
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | Phone Verification
            |--------------------------------------------------------------------------
            */

            Route::middleware(
                'api'
            )
                ->prefix(
                    'api'
                )
                ->group(
                    base_path(
                        'routes/auth-phone-verification.php'
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | Password Recovery / Change
            |--------------------------------------------------------------------------
            */

            Route::middleware(
                'api'
            )
                ->prefix(
                    'api'
                )
                ->group(
                    base_path(
                        'routes/auth-password.php'
                    )
                );
        },
    )

    ->withMiddleware(
        function (
            Middleware $middleware
        ): void {

            /*
            |--------------------------------------------------------------------------
            | Security Headers
            |--------------------------------------------------------------------------
            |
            | Apply security response headers to StyleHub.
            | Admin iframe restrictions are limited to admin pages.
            |
            */

            $middleware->append(
                AddSecurityHeaders::class
            );

            /*
            |--------------------------------------------------------------------------
            | API Error Normalization
            |--------------------------------------------------------------------------
            */

            $middleware->api(
                prepend: [
                    NormalizeApiErrorResponse::class,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Middleware Aliases
            |--------------------------------------------------------------------------
            */

            $middleware->alias([
                'account.active' =>
                    EnsureAccountIsActive::class,

                'role.admin' =>
                    EnsureUserIsAdmin::class,

                'role.customer' =>
                    EnsureUserIsCustomer::class,

                'shop-owner.approved' =>
                    EnsureShopOwnerHasApprovedShop::class,
            ]);
        }
    )

    ->withExceptions(
        function (
            Exceptions $exceptions
        ): void {

            $exceptions->shouldRenderJsonWhen(
                fn (
                    Request $request
                ): bool =>
                    $request->is(
                        'api/*'
                    )
                    ||
                    $request
                        ->expectsJson()
            );

            $exceptions->respond(
                function (
                    Response $response
                ): Response {
                    $request =
                        request();

                    if (
                        ! $request
                            instanceof Request
                    ) {
                        return $response;
                    }

                    return ApiErrorResponseNormalizer::normalize(
                        $request,
                        $response
                    );
                }
            );
        }
    )

    ->create();
