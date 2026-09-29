<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnverifiedPhoneNumberApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_number_update_requires_authentication(): void
    {
        $this->patchJson(
            '/api/v1/auth/phone-verification/phone-number',
            $this->payload()
        )
            ->assertUnauthorized();
    }

    public function test_admin_cannot_use_mobile_unverified_phone_recovery(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->unverifiedPhone()
                ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                $this->payload()
            )
            ->assertForbidden();
    }

    public function test_suspended_mobile_account_cannot_change_phone_number(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->suspended()
                ->create();

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                $this->payload()
            )
            ->assertForbidden();
    }

    public function test_verified_customer_cannot_change_phone_number_through_recovery_flow(): void
    {
        $user =
            User::factory()
                ->customer()
                ->create([
                    'phone_number' =>
                        '+639171111111',
                ]);

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                $this->payload()
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone_number',
            ]);

        $this->assertSame(
            '+639171111111',
            $user
                ->fresh()
                ->phone_number
        );
    }

    public function test_unverified_customer_can_correct_phone_number_with_current_password(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171111111',
                ]);

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                [
                    'phone_number' =>
                        '0917 222 2222',

                    'current_password' =>
                        'password',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.user.phone_number',
                '+639172222222'
            )
            ->assertJsonPath(
                'data.user.phone_verified',
                false
            );

        $freshUser =
            $user->fresh();

        $this->assertSame(
            '+639172222222',
            $freshUser->phone_number
        );

        $this->assertNull(
            $freshUser->phone_verified_at
        );
    }

    public function test_unverified_shop_owner_can_correct_phone_number(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171111111',
                ]);

        $this
            ->actingAs(
                $owner,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                $this->payload()
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.role',
                'shop_owner'
            )
            ->assertJsonPath(
                'data.user.phone_number',
                '+639172222222'
            )
            ->assertJsonPath(
                'data.user.phone_verified',
                false
            );
    }

    public function test_current_password_is_required_and_must_be_correct(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171111111',
                ]);

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                [
                    'phone_number' =>
                        '+639172222222',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                [
                    'phone_number' =>
                        '+639172222222',

                    'current_password' =>
                        'wrong-password',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);

        $this->assertSame(
            '+639171111111',
            $user
                ->fresh()
                ->phone_number
        );
    }

    public function test_duplicate_phone_number_is_rejected(): void
    {
        User::factory()
            ->customer()
            ->create([
                'phone_number' =>
                    '+639172222222',
            ]);

        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171111111',
                ]);

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                $this->payload()
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone_number',
            ]);

        $this->assertSame(
            '+639171111111',
            $user
                ->fresh()
                ->phone_number
        );
    }

    public function test_invalid_philippine_mobile_number_is_rejected(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create();

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                [
                    'phone_number' =>
                        '12345',

                    'current_password' =>
                        'password',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone_number',
            ]);
    }

    public function test_same_phone_number_is_rejected(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171111111',
                ]);

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                [
                    'phone_number' =>
                        '09171111111',

                    'current_password' =>
                        'password',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone_number',
            ]);
    }

    public function test_client_cannot_inject_verification_or_account_fields(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171111111',
                ]);

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                [
                    ...$this->payload(),

                    'phone_verified_at' =>
                        now()->toISOString(),

                    'role' =>
                        'admin',
                ]
            )
            ->assertUnprocessable();

        $freshUser =
            $user->fresh();

        $this->assertNull(
            $freshUser->phone_verified_at
        );

        $this->assertTrue(
            $freshUser->isCustomer()
        );
    }

    public function test_changing_phone_number_invalidates_any_existing_phone_otp(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171111111',
                ]);

        DB::table(
            'phone_verification_codes'
        )->insert([
            'user_id' =>
                $user->id,

            'phone_number' =>
                '09171111111',

            'code_hash' =>
                str_repeat(
                    'a',
                    64
                ),

            'attempts' =>
                0,

            'expires_at' =>
                now()->addMinutes(5),

            'last_sent_at' =>
                now(),

            'last_attempt_at' =>
                null,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                $this->payload()
            )
            ->assertOk();

        $this->assertDatabaseMissing(
            'phone_verification_codes',
            [
                'user_id' =>
                    $user->id,
            ]
        );
    }

    public function test_phone_recovery_keeps_current_token_and_revokes_other_sessions(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171111111',
                ]);

        $currentToken =
            $user->createToken(
                'current-device',
                [
                    'role:customer',
                ],
                now()->addDay()
            );

        $otherToken =
            $user->createToken(
                'other-device',
                [
                    'role:customer',
                ],
                now()->addDay()
            );

        $currentTokenId =
            $currentToken
                ->accessToken
                ->id;

        $otherTokenId =
            $otherToken
                ->accessToken
                ->id;

        $this
            ->withToken(
                $currentToken
                    ->plainTextToken
            )
            ->patchJson(
                '/api/v1/auth/phone-verification/phone-number',
                $this->payload()
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'id' =>
                    $currentTokenId,
            ]
        );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'id' =>
                    $otherTokenId,
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'phone_number' =>
                '09172222222',

            'current_password' =>
                'password',
        ];
    }
}