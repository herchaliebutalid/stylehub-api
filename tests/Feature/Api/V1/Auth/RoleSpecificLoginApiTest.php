<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleSpecificLoginApiTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD =
        'StyleHub#Secure2026';

    public function test_customer_can_login_using_customer_mobile_endpoint(): void
    {
        $customer =
            $this->user(
                UserRole::Customer
            );

        $this->postJson(
            '/api/v1/auth/customers/login',
            [
                'email' =>
                    $customer->email,

                'password' =>
                    self::PASSWORD,

                'device_name' =>
                    'Customer Android',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.user.id',
                $customer->id
            )
            ->assertJsonPath(
                'data.user.role',
                UserRole::Customer
                    ->value
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
    }

    public function test_shop_owner_can_login_using_shop_owner_mobile_endpoint(): void
    {
        $owner =
            $this->user(
                UserRole::ShopOwner
            );

        $this->postJson(
            '/api/v1/auth/shop-owners/login',
            [
                'email' =>
                    $owner->email,

                'password' =>
                    self::PASSWORD,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.user.role',
                UserRole::ShopOwner
                    ->value
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_admin_can_login_using_admin_web_endpoint(): void
    {
        $admin =
            $this->user(
                UserRole::Admin
            );

        $this->postJson(
            '/api/v1/admin/auth/login',
            [
                'email' =>
                    $admin->email,

                'password' =>
                    self::PASSWORD,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.user.role',
                UserRole::Admin
                    ->value
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_customer_cannot_login_using_shop_owner_endpoint(): void
    {
        $customer =
            $this->user(
                UserRole::Customer
            );

        $this->postJson(
            '/api/v1/auth/shop-owners/login',
            [
                'email' =>
                    $customer->email,

                'password' =>
                    self::PASSWORD,
            ]
        )
            ->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'The provided credentials are invalid.'
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_customer_cannot_login_using_admin_endpoint(): void
    {
        $customer =
            $this->user(
                UserRole::Customer
            );

        $this->postJson(
            '/api/v1/admin/auth/login',
            [
                'email' =>
                    $customer->email,

                'password' =>
                    self::PASSWORD,
            ]
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_shop_owner_cannot_login_using_customer_endpoint(): void
    {
        $owner =
            $this->user(
                UserRole::ShopOwner
            );

        $this->postJson(
            '/api/v1/auth/customers/login',
            [
                'email' =>
                    $owner->email,

                'password' =>
                    self::PASSWORD,
            ]
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_shop_owner_cannot_login_using_admin_endpoint(): void
    {
        $owner =
            $this->user(
                UserRole::ShopOwner
            );

        $this->postJson(
            '/api/v1/admin/auth/login',
            [
                'email' =>
                    $owner->email,

                'password' =>
                    self::PASSWORD,
            ]
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_admin_cannot_login_using_customer_mobile_endpoint(): void
    {
        $admin =
            $this->user(
                UserRole::Admin
            );

        $this->postJson(
            '/api/v1/auth/customers/login',
            [
                'email' =>
                    $admin->email,

                'password' =>
                    self::PASSWORD,
            ]
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_admin_cannot_login_using_shop_owner_mobile_endpoint(): void
    {
        $admin =
            $this->user(
                UserRole::Admin
            );

        $this->postJson(
            '/api/v1/auth/shop-owners/login',
            [
                'email' =>
                    $admin->email,

                'password' =>
                    self::PASSWORD,
            ]
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_invalid_password_uses_generic_error_message(): void
    {
        $customer =
            $this->user(
                UserRole::Customer
            );

        $this->postJson(
            '/api/v1/auth/customers/login',
            [
                'email' =>
                    $customer->email,

                'password' =>
                    'Wrong#Password2026',
            ]
        )
            ->assertUnauthorized()
            ->assertExactJson([
                'success' =>
                    false,

                'message' =>
                    'The provided credentials are invalid.',
            ]);
    }

    public function test_suspended_account_cannot_login(): void
    {
        $customer =
            $this->user(
                UserRole::Customer,
                UserStatus::Suspended
            );

        $this->postJson(
            '/api/v1/auth/customers/login',
            [
                'email' =>
                    $customer->email,

                'password' =>
                    self::PASSWORD,
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'This account is currently unavailable.'
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_login_normalizes_email_address(): void
    {
        $customer =
            $this->user(
                UserRole::Customer,
                UserStatus::Active,
                'customer@example.com'
            );

        $this->postJson(
            '/api/v1/auth/customers/login',
            [
                'email' =>
                    '  CUSTOMER@EXAMPLE.COM  ',

                'password' =>
                    self::PASSWORD,
            ]
        )->assertOk();
    }

    public function test_old_generic_login_endpoint_is_not_available(): void
    {
        $customer =
            $this->user(
                UserRole::Customer
            );

        $this->postJson(
            '/api/v1/auth/login',
            [
                'email' =>
                    $customer->email,

                'password' =>
                    self::PASSWORD,
            ]
        )->assertNotFound();
    }

    public function test_public_admin_registration_endpoint_does_not_exist(): void
    {
        $this->postJson(
            '/api/v1/admin/auth/register',
            [
                'email' =>
                    'attacker@example.com',

                'password' =>
                    self::PASSWORD,
            ]
        )->assertNotFound();
    }

    private function user(
        UserRole $role,
        UserStatus $status =
            UserStatus::Active,
        ?string $email = null
    ): User {
        return User::factory()
            ->create([
                'email' =>
                    $email
                    ?? fake()
                        ->unique()
                        ->safeEmail(),

                'role' =>
                    $role,

                'status' =>
                    $status,

                'password' =>
                    Hash::make(
                        self::PASSWORD
                    ),
            ]);
    }
}