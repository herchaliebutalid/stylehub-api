<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurePasswordRules();

        $this->configureRateLimiters();
    }

    private function configurePasswordRules(): void
    {
        Password::defaults(
            fn (): Password =>
                Password::min(12)
                    ->max(64)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
        );
    }

    private function configureRateLimiters(): void
    {
        $tooManyRequestsResponse =
            static function (
                Request $request,
                array $headers
            ) {
                return response()->json([
                    'success' =>
                        false,

                    'message' =>
                        'Too many requests. Please try again later.',
                ], 429, $headers);
            };

        /*
        |--------------------------------------------------------------------------
        | Login
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'auth-login',
            function (
                Request $request
            ) use (
                $tooManyRequestsResponse
            ): array {
                $email =
                    Str::lower(
                        trim(
                            (string) $request->input(
                                'email'
                            )
                        )
                    );

                $ipAddress =
                    $request->ip()
                    ?? 'unknown';

                return [
                    Limit::perMinute(5)
                        ->by(
                            'login-email-ip:'
                            .$email
                            .'|'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),

                    Limit::perMinute(30)
                        ->by(
                            'login-ip:'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Registration
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'auth-register',
            function (
                Request $request
            ) use (
                $tooManyRequestsResponse
            ): array {
                $ipAddress =
                    $request->ip()
                    ?? 'unknown';

                return [
                    Limit::perMinute(5)
                        ->by(
                            'register-minute-ip:'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),

                    Limit::perDay(50)
                        ->by(
                            'register-day-ip:'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Phone Verification Send
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'phone-verification-send',
            function (
                Request $request
            ) use (
                $tooManyRequestsResponse
            ): array {
                $userId =
                    $request
                        ->user()
                        ?->id
                    ?? 'guest';

                $ipAddress =
                    $request->ip()
                    ?? 'unknown';

                return [
                    Limit::perMinute(1)
                        ->by(
                            'phone-verification-send-user:'
                            .$userId
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),

                    Limit::perHour(5)
                        ->by(
                            'phone-verification-send-hour-user:'
                            .$userId
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),

                    Limit::perHour(20)
                        ->by(
                            'phone-verification-send-hour-ip:'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Phone Verification Verify
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'phone-verification-verify',
            function (
                Request $request
            ) use (
                $tooManyRequestsResponse
            ): array {
                $userId =
                    $request
                        ->user()
                        ?->id
                    ?? 'guest';

                $ipAddress =
                    $request->ip()
                    ?? 'unknown';

                return [
                    Limit::perMinute(10)
                        ->by(
                            'phone-verification-verify-user:'
                            .$userId
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),

                    Limit::perHour(60)
                        ->by(
                            'phone-verification-verify-ip:'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Password Reset Request
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'password-reset-request',
            function (
                Request $request
            ) use (
                $tooManyRequestsResponse
            ): array {
                $role =
                    trim(
                        (string) $request->input(
                            'role'
                        )
                    );

                $email =
                    Str::lower(
                        trim(
                            (string) $request->input(
                                'email'
                            )
                        )
                    );

                $ipAddress =
                    $request->ip()
                    ?? 'unknown';

                $identity =
                    $role
                    .'|'
                    .$email;

                return [
                    Limit::perMinute(1)
                        ->by(
                            'password-reset-request-minute:'
                            .$identity
                            .'|'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),

                    Limit::perHour(5)
                        ->by(
                            'password-reset-request-hour:'
                            .$identity
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),

                    Limit::perHour(20)
                        ->by(
                            'password-reset-request-ip:'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Password Reset Verification
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'password-reset-verify',
            function (
                Request $request
            ) use (
                $tooManyRequestsResponse
            ): array {
                $role =
                    trim(
                        (string) $request->input(
                            'role'
                        )
                    );

                $email =
                    Str::lower(
                        trim(
                            (string) $request->input(
                                'email'
                            )
                        )
                    );

                $ipAddress =
                    $request->ip()
                    ?? 'unknown';

                return [
                    Limit::perMinute(10)
                        ->by(
                            'password-reset-verify:'
                            .$role
                            .'|'
                            .$email
                            .'|'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),

                    Limit::perHour(60)
                        ->by(
                            'password-reset-verify-ip:'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Authenticated Password Change
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'password-change',
            function (
                Request $request
            ) use (
                $tooManyRequestsResponse
            ): array {
                $userId =
                    $request
                        ->user()
                        ?->id
                    ?? 'guest';

                $ipAddress =
                    $request->ip()
                    ?? 'unknown';

                return [
                    Limit::perMinute(5)
                        ->by(
                            'password-change-user:'
                            .$userId
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),

                    Limit::perHour(30)
                        ->by(
                            'password-change-ip:'
                            .$ipAddress
                        )
                        ->response(
                            $tooManyRequestsResponse
                        ),
                ];
            }
        );
    }
}