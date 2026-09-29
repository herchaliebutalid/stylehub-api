<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\ShopType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson(
            '/api/v1/auth/customers/register',
            $this->customerRegistrationPayload(
                email: 'customer@example.com'
            )
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.user.role',
                UserRole::Customer->value
            )
            ->assertJsonPath(
                'data.user.status',
                UserStatus::Active->value
            )
            ->assertJsonStructure([
                'success',
                'message',

                'data' => [
                    'token',
                    'token_type',
                    'expires_at',

                    'user' => [
                        'id',
                        'first_name',
                        'middle_name',
                        'last_name',
                        'full_name',
                        'email',
                        'phone_number',
                        'role',
                        'role_label',
                        'status',
                        'status_label',
                        'email_verified',
                        'last_login_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $this->assertDatabaseHas(
            'users',
            [
                'email' =>
                    'customer@example.com',

                'role' =>
                    UserRole::Customer->value,

                'status' =>
                    UserStatus::Active->value,

                'phone_number' =>
                    '+639171234567',
            ]
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_shop_owner_can_register_with_shop_and_shop_owner_role(): void
    {
        $response = $this->postJson(
            '/api/v1/auth/shop-owners/register',
            $this->shopOwnerRegistrationPayload(
                email: 'owner@example.com'
            )
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.user.role',
                UserRole::ShopOwner->value
            )
            ->assertJsonPath(
                'data.user.status',
                UserStatus::Active->value
            )
            ->assertJsonPath(
                'data.shop.type',
                ShopType::BarberShop->value
            )
            ->assertJsonPath(
                'data.shop.approval_status',
                ShopApprovalStatus::Pending->value
            )
            ->assertJsonPath(
                'data.shop.availability_status',
                ShopAvailabilityStatus::Closed->value
            );

        $this->assertDatabaseHas(
            'users',
            [
                'email' =>
                    'owner@example.com',

                'role' =>
                    UserRole::ShopOwner->value,

                'status' =>
                    UserStatus::Active->value,
            ]
        );

        $this->assertDatabaseHas(
            'shops',
            [
                'name' =>
                    'StyleHub Barber Studio',

                'type' =>
                    ShopType::BarberShop->value,

                'approval_status' =>
                    ShopApprovalStatus::Pending->value,

                'availability_status' =>
                    ShopAvailabilityStatus::Closed->value,
            ]
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_public_registration_cannot_assign_admin_role(): void
    {
        $payload =
            $this->customerRegistrationPayload(
                email:
                    'attacker@example.com'
            );

        $payload['role'] =
            UserRole::Admin->value;

        $this->postJson(
            '/api/v1/auth/customers/register',
            $payload
        )->assertUnprocessable();

        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'attacker@example.com',
            ]
        );
    }

    public function test_public_registration_cannot_assign_account_status(): void
    {
        $payload =
            $this->customerRegistrationPayload(
                email:
                    'attacker2@example.com'
            );

        $payload['status'] =
            UserStatus::Active->value;

        $this->postJson(
            '/api/v1/auth/customers/register',
            $payload
        )->assertUnprocessable();

        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'attacker2@example.com',
            ]
        );
    }

    public function test_duplicate_email_registration_is_rejected(): void
    {
        User::factory()
            ->customer()
            ->create([
                'email' =>
                    'existing@example.com',
            ]);

        $this->postJson(
            '/api/v1/auth/customers/register',
            $this->customerRegistrationPayload(
                email:
                    'existing@example.com'
            )
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_registration_normalizes_email_and_phone_number(): void
    {
        $payload =
            $this->customerRegistrationPayload(
                email:
                    'NORMALIZED@EXAMPLE.COM'
            );

        $payload['phone_number'] =
            '0917-123-4567';

        $response =
            $this->postJson(
                '/api/v1/auth/customers/register',
                $payload
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.user.email',
                'normalized@example.com'
            )
            ->assertJsonPath(
                'data.user.phone_number',
                '+639171234567'
            );

        $this->assertDatabaseHas(
            'users',
            [
                'email' =>
                    'normalized@example.com',

                'phone_number' =>
                    '+639171234567',
            ]
        );
    }

    public function test_password_is_never_returned_by_registration_api(): void
    {
        $response =
            $this->postJson(
                '/api/v1/auth/customers/register',
                $this->customerRegistrationPayload(
                    email:
                        'safe@example.com'
                )
            );

        $response->assertCreated();

        $this->assertArrayNotHasKey(
            'password',
            $response->json(
                'data.user'
            )
        );

        $this->assertArrayNotHasKey(
            'remember_token',
            $response->json(
                'data.user'
            )
        );
    }

    public function test_registered_password_is_hashed_in_database(): void
    {
        $password =
            'SecurePassword123!';

        $payload =
            $this->customerRegistrationPayload(
                email:
                    'password@example.com'
            );

        $payload['password'] =
            $password;

        $payload['password_confirmation'] =
            $password;

        $this->postJson(
            '/api/v1/auth/customers/register',
            $payload
        )->assertCreated();

        $user =
            User::query()
                ->where(
                    'email',
                    'password@example.com'
                )
                ->firstOrFail();

        $this->assertNotSame(
            $password,
            $user->getRawOriginal(
                'password'
            )
        );

        $this->assertTrue(
            Hash::check(
                $password,
                $user->getRawOriginal(
                    'password'
                )
            )
        );
    }

    public function test_customer_can_login_with_valid_credentials(): void
    {
        $password =
            'SecurePassword123!';

        $user =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'login@example.com',

                    'password' =>
                        $password,
                ]);

        $response =
            $this->postJson(
                '/api/v1/auth/customers/login',
                [
                    'email' =>
                        'LOGIN@EXAMPLE.COM',

                    'password' =>
                        $password,

                    'device_name' =>
                        'StyleHub Test Device',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.user.id',
                $user->id
            )
            ->assertJsonPath(
                'data.user.role',
                UserRole::Customer->value
            )
            ->assertJsonPath(
                'data.token_type',
                'Bearer'
            )
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_at',
                    'user',
                ],
            ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );

        $this->assertNotNull(
            $user
                ->fresh()
                ->last_login_at
        );
    }

    public function test_invalid_customer_login_uses_generic_error_message(): void
    {
        User::factory()
            ->customer()
            ->create([
                'email' =>
                    'real@example.com',

                'password' =>
                    'SecurePassword123!',
            ]);

        $this->postJson(
            '/api/v1/auth/customers/login',
            [
                'email' =>
                    'real@example.com',

                'password' =>
                    'WrongPassword123!',

                'device_name' =>
                    'Test Device',
            ]
        )
            ->assertUnauthorized()
            ->assertExactJson([
                'success' =>
                    false,

                'message' =>
                    'The provided credentials are invalid.',
            ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_suspended_customer_cannot_login(): void
    {
        User::factory()
            ->customer()
            ->suspended()
            ->create([
                'email' =>
                    'suspended@example.com',

                'password' =>
                    'SecurePassword123!',
            ]);

        $this->postJson(
            '/api/v1/auth/customers/login',
            [
                'email' =>
                    'suspended@example.com',

                'password' =>
                    'SecurePassword123!',

                'device_name' =>
                    'Test Device',
            ]
        )
            ->assertForbidden()
            ->assertExactJson([
                'success' =>
                    false,

                'message' =>
                    'This account is currently unavailable.',
            ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authenticated Profile
    |--------------------------------------------------------------------------
    */

    public function test_me_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/auth/me'
        )->assertUnauthorized();
    }

    public function test_authenticated_customer_can_get_own_profile_without_shop_data(): void
    {
        $user =
            User::factory()
                ->customer()
                ->create();

        $token =
            $user->createToken(
                'Test Device',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.user.id',
                $user->id
            )
            ->assertJsonPath(
                'data.user.role',
                UserRole::Customer->value
            )
            ->assertJsonMissingPath(
                'data.user.password'
            )
            ->assertJsonMissingPath(
                'data.user.remember_token'
            )
            ->assertJsonMissingPath(
                'data.shop'
            );
    }

    public function test_pending_shop_owner_can_get_own_shop_from_me_endpoint(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->createShopOwnerWithShop(
                approvalStatus:
                    ShopApprovalStatus::Pending,

                email:
                    'pending-owner@example.com'
            );

        $token =
            $owner->createToken(
                'Pending Owner Device',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.user.id',
                $owner->id
            )
            ->assertJsonPath(
                'data.user.role',
                UserRole::ShopOwner->value
            )
            ->assertJsonPath(
                'data.shop.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.shop.name',
                $shop->name
            )
            ->assertJsonPath(
                'data.shop.approval_status',
                ShopApprovalStatus::Pending->value
            )
            ->assertJsonPath(
                'data.shop.approval_status_label',
                ShopApprovalStatus::Pending->label()
            )
            ->assertJsonPath(
                'data.shop.availability_status',
                ShopAvailabilityStatus::Closed->value
            )
            ->assertJsonPath(
                'data.shop.rejection_reason',
                null
            );
    }

    public function test_approved_shop_owner_can_get_approved_shop_state_from_me_endpoint(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->createShopOwnerWithShop(
                approvalStatus:
                    ShopApprovalStatus::Approved,

                email:
                    'approved-owner@example.com'
            );

        $token =
            $owner->createToken(
                'Approved Owner Device',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.id',
                $owner->id
            )
            ->assertJsonPath(
                'data.shop.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.shop.approval_status',
                ShopApprovalStatus::Approved->value
            )
            ->assertJsonPath(
                'data.shop.approval_status_label',
                ShopApprovalStatus::Approved->label()
            )
            ->assertJsonPath(
                'data.shop.rejection_reason',
                null
            );
    }

    public function test_rejected_shop_owner_can_get_rejection_state_and_reason_from_me_endpoint(): void
    {
        $rejectionReason =
            'Business registration information requires clarification.';

        [
            $owner,
            $shop,
        ] =
            $this->createShopOwnerWithShop(
                approvalStatus:
                    ShopApprovalStatus::Rejected,

                email:
                    'rejected-owner@example.com',

                rejectionReason:
                    $rejectionReason
            );

        $token =
            $owner->createToken(
                'Rejected Owner Device',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.id',
                $owner->id
            )
            ->assertJsonPath(
                'data.shop.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.shop.approval_status',
                ShopApprovalStatus::Rejected->value
            )
            ->assertJsonPath(
                'data.shop.approval_status_label',
                ShopApprovalStatus::Rejected->label()
            )
            ->assertJsonPath(
                'data.shop.rejection_reason',
                $rejectionReason
            );
    }

    public function test_shop_owner_me_endpoint_returns_only_the_authenticated_owners_shop(): void
    {
        [
            $firstOwner,
            $firstShop,
        ] =
            $this->createShopOwnerWithShop(
                approvalStatus:
                    ShopApprovalStatus::Pending,

                email:
                    'first-owner@example.com'
            );

        [
            $secondOwner,
            $secondShop,
        ] =
            $this->createShopOwnerWithShop(
                approvalStatus:
                    ShopApprovalStatus::Approved,

                email:
                    'second-owner@example.com'
            );

        $token =
            $firstOwner->createToken(
                'First Owner Device',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

        $response =
            $this
                ->withToken(
                    $token
                )
                ->getJson(
                    '/api/v1/auth/me'
                );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.user.id',
                $firstOwner->id
            )
            ->assertJsonPath(
                'data.shop.id',
                $firstShop->id
            )
            ->assertJsonPath(
                'data.shop.name',
                $firstShop->name
            );

        $this->assertNotSame(
            $secondOwner->id,
            $response->json(
                'data.user.id'
            )
        );

        $this->assertNotSame(
            $secondShop->id,
            $response->json(
                'data.shop.id'
            )
        );
    }

    public function test_shop_owner_without_shop_receives_null_shop_from_me_endpoint(): void
    {
        $owner =
            User::factory()
                ->create([
                    'email' =>
                        'owner-without-shop@example.com',
                ]);

        $owner->role =
            UserRole::ShopOwner;

        $owner->status =
            UserStatus::Active;

        $owner->save();

        $token =
            $owner->createToken(
                'Owner Without Shop Device',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.id',
                $owner->id
            )
            ->assertJsonPath(
                'data.user.role',
                UserRole::ShopOwner->value
            )
            ->assertJsonPath(
                'data.shop',
                null
            );
    }

    public function test_suspended_user_with_existing_token_is_blocked(): void
    {
        $user =
            User::factory()
                ->customer()
                ->create();

        $token =
            $user->createToken(
                'Existing Device',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

        $user->status =
            UserStatus::Suspended;

        $user->save();

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertForbidden()
            ->assertJson([
                'success' =>
                    false,

                'message' =>
                    'This account is currently suspended.',
            ]);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user =
            User::factory()
                ->customer()
                ->create();

        $token =
            $user->createToken(
                'Logout Device',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

        $this
            ->withToken(
                $token
            )
            ->postJson(
                '/api/v1/auth/logout'
            )
            ->assertOk()
            ->assertJson([
                'success' =>
                    true,

                'message' =>
                    'Logout successful.',
            ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );

        /*
         * Laravel's test container persists between requests made
         * within the same test method.
         *
         * Clear resolved guards so the next request behaves like
         * a fresh HTTP request.
         */
        Auth::forgetGuards();

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertUnauthorized();
    }

    public function test_there_is_no_public_admin_registration_endpoint(): void
    {
        $this->postJson(
            '/api/v1/admin/auth/register',
            $this->customerRegistrationPayload(
                email:
                    'fake-admin@example.com'
            )
        )->assertNotFound();

        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'fake-admin@example.com',
            ]
        );
    }

    public function test_repeated_invalid_customer_login_attempts_are_rate_limited(): void
    {
        User::factory()
            ->customer()
            ->create([
                'email' =>
                    'throttle@example.com',

                'password' =>
                    'SecurePassword123!',
            ]);

        for (
            $attempt = 1;
            $attempt <= 5;
            $attempt++
        ) {
            $this->postJson(
                '/api/v1/auth/customers/login',
                [
                    'email' =>
                        'throttle@example.com',

                    'password' =>
                        'WrongPassword123!',

                    'device_name' =>
                        'Test Device',
                ]
            )->assertUnauthorized();
        }

        $this->postJson(
            '/api/v1/auth/customers/login',
            [
                'email' =>
                    'throttle@example.com',

                'password' =>
                    'WrongPassword123!',

                'device_name' =>
                    'Test Device',
            ]
        )
            ->assertStatus(
                429
            )
            ->assertJson([
                'success' =>
                    false,

                'message' =>
                    'Too many requests. Please try again later.',
            ]);
    }

    /**
     * Build a valid customer registration request.
     *
     * @return array<string, mixed>
     */
    private function customerRegistrationPayload(
        string $email
    ): array {
        return [
            'first_name' =>
                'Juan',

            'middle_name' =>
                'Santos',

            'last_name' =>
                'Dela Cruz',

            'email' =>
                $email,

            'phone_number' =>
                '09171234567',

            'password' =>
                'SecurePassword123!',

            'password_confirmation' =>
                'SecurePassword123!',

            'device_name' =>
                'StyleHub Test Device',
        ];
    }

    /**
     * Build a valid shop-owner registration request.
     *
     * @return array<string, mixed>
     */
    private function shopOwnerRegistrationPayload(
        string $email
    ): array {
        return [
            'first_name' =>
                'Maria',

            'middle_name' =>
                'Santos',

            'last_name' =>
                'Reyes',

            'email' =>
                $email,

            'phone_number' =>
                '09171234567',

            'password' =>
                'SecurePassword123!',

            'password_confirmation' =>
                'SecurePassword123!',

            'device_name' =>
                'StyleHub Test Device',

            'shop' => [
                'name' =>
                    'StyleHub Barber Studio',

                'type' =>
                    ShopType::BarberShop->value,

                'description' =>
                    'A test barber shop.',

                'phone_number' =>
                    '09181234567',

                'email' =>
                    'shop@stylehub.test',

                'address' =>
                    '123 Example Street',

                'city' =>
                    'Surigao City',

                'province' =>
                    'Surigao del Norte',
            ],
        ];
    }

    /**
     * Create a shop-owner account together with one controlled shop.
     *
     * Sensitive state such as role, account status, ownership and
     * approval status is assigned explicitly by trusted test code.
     *
     * @return array{0: User, 1: Shop}
     */
    private function createShopOwnerWithShop(
        ShopApprovalStatus $approvalStatus,
        string $email,
        ?string $rejectionReason = null
    ): array {
        $owner =
            User::factory()
                ->create([
                    'email' =>
                        $email,
                ]);

        $owner->role =
            UserRole::ShopOwner;

        $owner->status =
            UserStatus::Active;

        $owner->save();

        $shop =
            new Shop([
                'name' =>
                    'Shop '.$owner->id,

                'type' =>
                    ShopType::BarberShop,

                'description' =>
                    'Shop used for authentication API testing.',

                'phone_number' =>
                    '+639181234567',

                'email' =>
                    'shop-'.$owner->id.'@stylehub.test',

                'address' =>
                    '123 Test Street',

                'city' =>
                    'Surigao City',

                'province' =>
                    'Surigao del Norte',
            ]);

        /*
         * These fields are intentionally not mass assignable.
         * Assign them explicitly to mirror production security rules.
         */
        $shop->owner_id =
            $owner->id;

        $shop->approval_status =
            $approvalStatus;

        $shop->availability_status =
            ShopAvailabilityStatus::Closed;

        $shop->reviewed_by =
            null;

        $shop->reviewed_at =
            $approvalStatus ===
            ShopApprovalStatus::Pending
                ? null
                : now();

        $shop->rejection_reason =
            $approvalStatus ===
            ShopApprovalStatus::Rejected
                ? $rejectionReason
                : null;

        $shop->save();

        return [
            $owner,
            $shop,
        ];
    }
}