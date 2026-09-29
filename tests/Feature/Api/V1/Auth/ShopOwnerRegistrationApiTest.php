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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShopOwnerRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_owner_registration_creates_user_shop_and_token(): void
    {
        $response = $this->postJson(
            '/api/v1/auth/shop-owners/register',
            $this->validPayload()
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
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
            )
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'expires_at',
                    'user',
                    'shop',
                ],
            ]);

        $user = User::query()
            ->where(
                'email',
                'owner@stylehub.test'
            )
            ->firstOrFail();

        $shop = Shop::query()
            ->where(
                'owner_id',
                $user->id
            )
            ->firstOrFail();

        $this->assertSame(
            UserRole::ShopOwner,
            $user->role
        );

        $this->assertSame(
            ShopApprovalStatus::Pending,
            $shop->approval_status
        );

        $this->assertSame(
            ShopAvailabilityStatus::Closed,
            $shop->availability_status
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_shop_owner_registration_normalizes_phone_numbers_and_emails(): void
    {
        $payload = $this->validPayload();

        $payload['email'] =
            'OWNER.NORMALIZED@STYLEHUB.TEST';

        $payload['phone_number'] =
            '0917-123-4567';

        $payload['shop']['email'] =
            'MYSHOP@STYLEHUB.TEST';

        $payload['shop']['phone_number'] =
            '0918-123-4567';

        $response = $this->postJson(
            '/api/v1/auth/shop-owners/register',
            $payload
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.user.email',
                'owner.normalized@stylehub.test'
            )
            ->assertJsonPath(
                'data.user.phone_number',
                '+639171234567'
            )
            ->assertJsonPath(
                'data.shop.email',
                'myshop@stylehub.test'
            )
            ->assertJsonPath(
                'data.shop.phone_number',
                '+639181234567'
            );
    }

    public function test_shop_owner_cannot_self_approve_during_registration(): void
    {
        $payload = $this->validPayload();

        $payload['shop']['approval_status'] =
            ShopApprovalStatus::Approved->value;

        $this->postJson(
            '/api/v1/auth/shop-owners/register',
            $payload
        )->assertUnprocessable();

        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'owner@stylehub.test',
            ]
        );

        $this->assertDatabaseCount(
            'shops',
            0
        );
    }

    public function test_shop_owner_cannot_inject_open_availability_during_registration(): void
    {
        $payload = $this->validPayload();

        $payload['shop']['availability_status'] =
            ShopAvailabilityStatus::Open->value;

        $this->postJson(
            '/api/v1/auth/shop-owners/register',
            $payload
        )->assertUnprocessable();

        $this->assertDatabaseCount(
            'users',
            0
        );

        $this->assertDatabaseCount(
            'shops',
            0
        );
    }

    public function test_invalid_shop_data_prevents_account_creation(): void
    {
        $payload = $this->validPayload();

        $payload['shop']['name'] = '';

        $this->postJson(
            '/api/v1/auth/shop-owners/register',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shop.name',
            ]);

        $this->assertDatabaseCount(
            'users',
            0
        );

        $this->assertDatabaseCount(
            'shops',
            0
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_shop_owner_password_is_hashed(): void
    {
        $password =
            'SecurePassword123!';

        $payload =
            $this->validPayload();

        $payload['password'] =
            $password;

        $payload['password_confirmation'] =
            $password;

        $this->postJson(
            '/api/v1/auth/shop-owners/register',
            $payload
        )->assertCreated();

        $user = User::query()
            ->where(
                'email',
                'owner@stylehub.test'
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

    /**
     * Build a valid shop-owner registration payload.
     *
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Reyes',

            'email' =>
                'owner@stylehub.test',

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
}