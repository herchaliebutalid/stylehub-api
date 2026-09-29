<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterShopOwnerRequest;
use App\Http\Requests\Api\V1\Auth\RegisterUserRequest;
use App\Http\Resources\Api\V1\ShopResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    private const TOKEN_EXPIRATION_DAYS = 30;

    /**
     * Register a customer account.
     */
    public function registerCustomer(
        RegisterUserRequest $request
    ): JsonResponse {
        return $this->registerCustomerUser(
            request: $request,
        );
    }

    /**
     * Register a shop-owner account together with its shop.
     *
     * The account, shop and Sanctum token are created inside one
     * database transaction. A failure in any part rolls back the
     * complete registration.
     */
    public function registerShopOwner(
        RegisterShopOwnerRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        [
            $user,
            $shop,
            $plainTextToken,
            $expiresAt,
        ] = DB::transaction(
            function () use ($validated): array {
                $user = new User([
                    'first_name' =>
                        $validated['first_name'],

                    'middle_name' =>
                        $validated['middle_name'],

                    'last_name' =>
                        $validated['last_name'],

                    'email' =>
                        $validated['email'],

                    'phone_number' =>
                        $validated['phone_number'],

                    'password' =>
                        $validated['password'],
                ]);

                /*
                 * Security:
                 * Role and status are assigned by trusted backend code.
                 * They are never taken from client input.
                 */
                $user->role =
                    UserRole::ShopOwner;

                $user->status =
                    UserStatus::Active;

                $user->save();

                $shopData =
                    $validated['shop'];

                $shop = new Shop([
                    'name' =>
                        $shopData['name'],

                    'type' =>
                        $shopData['type'],

                    'description' =>
                        $shopData['description'],

                    'phone_number' =>
                        $shopData['phone_number'],

                    'email' =>
                        $shopData['email'],

                    'address' =>
                        $shopData['address'],

                    'city' =>
                        $shopData['city'],

                    'province' =>
                        $shopData['province'],
                ]);

                /*
                 * Security-sensitive shop state is explicitly assigned.
                 * A newly submitted business can never self-approve.
                 */
                $shop->owner_id =
                    $user->id;

                $shop->approval_status =
                    ShopApprovalStatus::Pending;

                $shop->availability_status =
                    ShopAvailabilityStatus::Closed;

                $shop->reviewed_by =
                    null;

                $shop->reviewed_at =
                    null;

                $shop->rejection_reason =
                    null;

                $shop->save();

                $expiresAt =
                    now()->addDays(
                        self::TOKEN_EXPIRATION_DAYS
                    );

                $token =
                    $user->createToken(
                        $validated['device_name'],
                        ['*'],
                        $expiresAt
                    );

                return [
                    $user,
                    $shop,
                    $token->plainTextToken,
                    $expiresAt,
                ];
            }
        );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Shop owner registration submitted successfully. The shop is pending administrator approval.',

            'data' => [
                'token' =>
                    $plainTextToken,

                'token_type' =>
                    'Bearer',

                'expires_at' =>
                    $expiresAt->toISOString(),

                'user' => (
                    new UserResource(
                        $user
                    )
                )->resolve(
                    $request
                ),

                'shop' => (
                    new ShopResource(
                        $shop
                    )
                )->resolve(
                    $request
                ),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Authenticate an existing account.
     */
    public function login(
        LoginRequest $request
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

        if (
            $user === null
            || ! Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'The provided credentials are incorrect.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (
            ! $user->isActive()
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'This account is currently suspended.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (
            Hash::needsRehash(
                $user->password
            )
        ) {
            $user->password =
                $validated['password'];
        }

        $user->last_login_at =
            now();

        $user->save();

        $expiresAt =
            now()->addDays(
                self::TOKEN_EXPIRATION_DAYS
            );

        $token =
            $user->createToken(
                $validated['device_name'],
                ['*'],
                $expiresAt
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Login successful.',

            'data' => [
                'token' =>
                    $token->plainTextToken,

                'token_type' =>
                    'Bearer',

                'expires_at' =>
                    $expiresAt->toISOString(),

                'user' => (
                    new UserResource(
                        $user
                    )
                )->resolve(
                    $request
                ),
            ],
        ]);
    }

    /**
     * Return the currently authenticated mobile account.
     *
     * Customers receive only their safe user representation.
     *
     * Shop owners additionally receive their own shop so the mobile
     * application can determine whether the shop is:
     *
     * - pending administrator approval;
     * - approved;
     * - rejected.
     *
     * This endpoint deliberately does not require the
     * shop-owner.approved middleware because pending and rejected
     * shop owners must still be able to see their registration state.
     */
    public function me(
        Request $request
    ): JsonResponse {
        $user =
            $request->user();

        $data = [
            'user' => (
                new UserResource(
                    $user
                )
            )->resolve(
                $request
            ),
        ];

        if (
            $user->isShopOwner()
        ) {
            /*
             * Load only the shop belonging to the authenticated
             * shop-owner account.
             *
             * The HasOne relationship prevents clients from selecting
             * or probing another owner's shop.
             */
            $user->loadMissing(
                'shop'
            );

            $data['shop'] =
                $user->shop === null
                    ? null
                    : (
                        new ShopResource(
                            $user->shop
                        )
                    )->resolve(
                        $request
                    );
        }

        return response()->json([
            'success' =>
                true,

            'data' =>
                $data,
        ]);
    }

    /**
     * Revoke the Sanctum token used for this request.
     */
    public function logout(
        Request $request
    ): JsonResponse {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Logout successful.',
        ]);
    }

    /**
     * Create a customer account.
     */
    private function registerCustomerUser(
        RegisterUserRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        [
            $user,
            $plainTextToken,
            $expiresAt,
        ] = DB::transaction(
            function () use ($validated): array {
                $user = new User([
                    'first_name' =>
                        $validated['first_name'],

                    'middle_name' =>
                        $validated['middle_name'],

                    'last_name' =>
                        $validated['last_name'],

                    'email' =>
                        $validated['email'],

                    'phone_number' =>
                        $validated['phone_number'],

                    'password' =>
                        $validated['password'],
                ]);

                $user->role =
                    UserRole::Customer;

                $user->status =
                    UserStatus::Active;

                $user->save();

                $expiresAt =
                    now()->addDays(
                        self::TOKEN_EXPIRATION_DAYS
                    );

                $token =
                    $user->createToken(
                        $validated['device_name'],
                        ['*'],
                        $expiresAt
                    );

                return [
                    $user,
                    $token->plainTextToken,
                    $expiresAt,
                ];
            }
        );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Customer account created successfully.',

            'data' => [
                'token' =>
                    $plainTextToken,

                'token_type' =>
                    'Bearer',

                'expires_at' =>
                    $expiresAt->toISOString(),

                'user' => (
                    new UserResource(
                        $user
                    )
                )->resolve(
                    $request
                ),
            ],
        ], Response::HTTP_CREATED);
    }
}