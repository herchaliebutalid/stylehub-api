<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RoleLoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RoleLoginController extends Controller
{
    /**
     * Customer mobile login.
     */
    public function customer(
        RoleLoginRequest $request
    ): JsonResponse {
        return $this->loginForRole(
            request:
                $request,

            requiredRole:
                UserRole::Customer,

            defaultDeviceName:
                'stylehub-mobile-customer',
        );
    }

    /**
     * Shop Owner mobile login.
     *
     * A pending Shop Owner may authenticate.
     *
     * Actual shop-management routes remain protected by the
     * shop-owner.approved middleware.
     */
    public function shopOwner(
        RoleLoginRequest $request
    ): JsonResponse {
        return $this->loginForRole(
            request:
                $request,

            requiredRole:
                UserRole::ShopOwner,

            defaultDeviceName:
                'stylehub-mobile-shop-owner',
        );
    }

    /**
     * Administrator web login.
     */
    public function admin(
        RoleLoginRequest $request
    ): JsonResponse {
        return $this->loginForRole(
            request:
                $request,

            requiredRole:
                UserRole::Admin,

            defaultDeviceName:
                'stylehub-admin-web',
        );
    }

    /**
     * Authenticate exactly one expected account role.
     */
    private function loginForRole(
        RoleLoginRequest $request,
        UserRole $requiredRole,
        string $defaultDeviceName
    ): JsonResponse {
        $validated =
            $request->validated();

        $user =
            User::query()
                ->where(
                    'email',
                    $validated['email']
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Generic Authentication Failure
        |--------------------------------------------------------------------------
        |
        | The response is intentionally identical when:
        |
        | - email does not exist;
        | - password is incorrect;
        | - account belongs to another role.
        |
        | This avoids leaking unnecessary account information.
        |
        */

        if (
            $user === null
            || ! Hash::check(
                $validated['password'],
                $user->password
            )
            || $user->role
                !== $requiredRole
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'The provided credentials are invalid.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Account Status
        |--------------------------------------------------------------------------
        */

        if (
            $user->status
            !== UserStatus::Active
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'This account is currently unavailable.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Device Name
        |--------------------------------------------------------------------------
        */

        $deviceName =
            $validated[
                'device_name'
            ]
            ?? $defaultDeviceName;

        if (
            ! is_string(
                $deviceName
            )
            || trim(
                $deviceName
            ) === ''
        ) {
            $deviceName =
                $defaultDeviceName;
        }

        /*
        |--------------------------------------------------------------------------
        | Record Successful Login
        |--------------------------------------------------------------------------
        */

        $user->forceFill([
            'last_login_at' =>
                now(),
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Role-Specific Token Ability
        |--------------------------------------------------------------------------
        */

        $abilities =
            match (
                $requiredRole
            ) {
                UserRole::Customer => [
                    'role:customer',
                ],

                UserRole::ShopOwner => [
                    'role:shop_owner',
                ],

                UserRole::Admin => [
                    'role:admin',
                ],
            };

        /*
        |--------------------------------------------------------------------------
        | 30-Day Sanctum Token
        |--------------------------------------------------------------------------
        */

        $expiresAt =
            now()->addDays(
                30
            );

        $newToken =
            $user->createToken(
                name:
                    $deviceName,

                abilities:
                    $abilities,

                expiresAt:
                    $expiresAt,
            );

        /*
        |--------------------------------------------------------------------------
        | Safe User Response
        |--------------------------------------------------------------------------
        |
        | Always use the shared UserResource here instead of manually creating
        | a smaller user object.
        |
        | The mobile application requires fields such as:
        |
        | - phone_number
        | - phone_verified
        | - profile information
        | - role
        | - status
        |
        | Using one shared resource keeps login, /auth/me, registration and
        | phone-verification responses consistent.
        |
        */

        $userResource =
            (
                new UserResource(
                    $user
                )
            )->resolve(
                $request
            );

        /*
        |--------------------------------------------------------------------------
        | Login Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Login successful.',

            'data' => [
                'token' =>
                    $newToken
                        ->plainTextToken,

                'token_type' =>
                    'Bearer',

                'expires_at' =>
                    $expiresAt
                        ->toISOString(),

                'user' =>
                    $userResource,
            ],
        ]);
    }
}