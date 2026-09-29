<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerPersonalInformationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_requires_authentication(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->patchJson(
            '/api/v1/customer/profile',
            $this->profilePayload(
                $customer
            )
        )->assertUnauthorized();
    }

    public function test_shop_owner_and_admin_cannot_update_customer_personal_information(): void
    {
        foreach (
            [
                User::factory()
                    ->shopOwner()
                    ->create(),

                User::factory()
                    ->admin()
                    ->create(),
            ] as $user
        ) {
            $this->actingAs(
                $user,
                'sanctum'
            )
                ->patchJson(
                    '/api/v1/customer/profile',
                    $this->profilePayload(
                        $user
                    )
                )
                ->assertForbidden();
        }
    }

    public function test_suspended_customer_cannot_update_personal_information(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->suspended()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer
                )
            )
            ->assertForbidden();
    }

    public function test_unverified_phone_customer_cannot_use_protected_profile_update(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer
                )
            )
            ->assertForbidden();
    }

    public function test_customer_can_update_names_without_current_password_and_values_are_normalized(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'first_name' =>
                        'Old',

                    'middle_name' =>
                        'Middle',

                    'last_name' =>
                        'Name',

                    'email' =>
                        'customer@example.com',

                    'phone_number' =>
                        '+639171111111',
                ]);

        $originalEmailVerifiedAt =
            $customer->email_verified_at;

        $originalPhoneVerifiedAt =
            $customer->phone_verified_at;

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                [
                    'first_name' =>
                        '  Juan   Miguel  ',

                    'middle_name' =>
                        '  Santos   Reyes  ',

                    'last_name' =>
                        '  Cruz  ',

                    'email' =>
                        ' CUSTOMER@EXAMPLE.COM ',

                    'phone_number' =>
                        '0917 111 1111',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.user.first_name',
                'Juan Miguel'
            )
            ->assertJsonPath(
                'data.user.middle_name',
                'Santos Reyes'
            )
            ->assertJsonPath(
                'data.user.last_name',
                'Cruz'
            )
            ->assertJsonPath(
                'data.user.email',
                'customer@example.com'
            )
            ->assertJsonPath(
                'data.user.phone_number',
                '+639171111111'
            )
            ->assertJsonPath(
                'data.user.phone_verified',
                true
            )
            ->assertJsonPath(
                'data.user.email_verified',
                true
            )
            ->assertJsonPath(
                'data.requires_phone_verification',
                false
            );

        $fresh =
            $customer->fresh();

        $this->assertSame(
            'Juan Miguel',
            $fresh->first_name
        );

        $this->assertSame(
            'Santos Reyes',
            $fresh->middle_name
        );

        $this->assertSame(
            'Cruz',
            $fresh->last_name
        );

        $this->assertTrue(
            $fresh->email_verified_at
                ->equalTo(
                    $originalEmailVerifiedAt
                )
        );

        $this->assertTrue(
            $fresh->phone_verified_at
                ->equalTo(
                    $originalPhoneVerifiedAt
                )
        );
    }

    public function test_blank_middle_name_is_saved_as_null(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'middle_name' =>
                        'Old Middle',
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'middle_name' =>
                            '   ',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.middle_name',
                null
            );

        $this->assertNull(
            $customer
                ->fresh()
                ->middle_name
        );
    }

    public function test_email_change_requires_correct_current_password(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'old-email@example.com',

                    'phone_number' =>
                        '+639171111111',

                    'password' =>
                        'CorrectPassword123!',
                ]);

        $newEmail =
            'new-email@example.com';

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'email' =>
                            $newEmail,
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'email' =>
                            $newEmail,

                        'current_password' =>
                            'WrongPassword123!',
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);

        $this->assertSame(
            'old-email@example.com',
            $customer
                ->fresh()
                ->email
        );
    }

    public function test_email_change_normalizes_email_resets_email_verification_and_revokes_other_sessions(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'old-email@example.com',

                    'phone_number' =>
                        '+639171111111',

                    'password' =>
                        'CorrectPassword123!',
                ]);

        $currentToken =
            $customer->createToken(
                'current-device',
                [
                    'role:customer',
                ],
                now()->addDay()
            );

        $otherToken =
            $customer->createToken(
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

        $this->withToken(
            $currentToken
                ->plainTextToken
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'email' =>
                            '  NEW.EMAIL@EXAMPLE.COM  ',

                        'current_password' =>
                            'CorrectPassword123!',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.email',
                'new.email@example.com'
            )
            ->assertJsonPath(
                'data.user.email_verified',
                false
            )
            ->assertJsonPath(
                'data.user.phone_verified',
                true
            )
            ->assertJsonPath(
                'data.requires_phone_verification',
                false
            );

        $fresh =
            $customer->fresh();

        $this->assertSame(
            'new.email@example.com',
            $fresh->email
        );

        $this->assertNull(
            $fresh->email_verified_at
        );

        $this->assertNotNull(
            $fresh->phone_verified_at
        );

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

    public function test_phone_change_requires_current_password(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'phone_number' =>
                        '+639171111111',

                    'password' =>
                        'CorrectPassword123!',
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'phone_number' =>
                            '0917 222 2222',
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);

        $this->assertSame(
            '+639171111111',
            $customer
                ->fresh()
                ->phone_number
        );
    }

    public function test_phone_change_resets_verification_invalidates_old_otp_and_revokes_other_sessions(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'customer@example.com',

                    'phone_number' =>
                        '+639171111111',

                    'password' =>
                        'CorrectPassword123!',
                ]);

        DB::table(
            'phone_verification_codes'
        )->insert([
            'user_id' =>
                $customer->id,

            'phone_number' =>
                '+639171111111',

            'code_hash' =>
                hash(
                    'sha256',
                    '123456'
                ),

            'attempts' =>
                0,

            'expires_at' =>
                now()->addMinutes(10),

            'last_sent_at' =>
                now(),

            'last_attempt_at' =>
                null,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $currentToken =
            $customer->createToken(
                'current-device',
                [
                    'role:customer',
                ],
                now()->addDay()
            );

        $otherToken =
            $customer->createToken(
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

        $this->withToken(
            $currentToken
                ->plainTextToken
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'phone_number' =>
                            '0917 222 2222',

                        'current_password' =>
                            'CorrectPassword123!',
                    ]
                )
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
            )
            ->assertJsonPath(
                'data.user.email_verified',
                true
            )
            ->assertJsonPath(
                'data.requires_phone_verification',
                true
            );

        $fresh =
            $customer->fresh();

        $this->assertSame(
            '+639172222222',
            $fresh->phone_number
        );

        $this->assertNull(
            $fresh->phone_verified_at
        );

        $this->assertNotNull(
            $fresh->email_verified_at
        );

        $this->assertDatabaseMissing(
            'phone_verification_codes',
            [
                'user_id' =>
                    $customer->id,
            ]
        );

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

    public function test_duplicate_email_and_phone_number_are_rejected(): void
    {
        $otherUser =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'already-used@example.com',

                    'phone_number' =>
                        '+639179999999',
                ]);

        $customer =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'customer@example.com',

                    'phone_number' =>
                        '+639171111111',

                    'password' =>
                        'CorrectPassword123!',
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'email' =>
                            $otherUser->email,

                        'current_password' =>
                            'CorrectPassword123!',
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'phone_number' =>
                            $otherUser->phone_number,

                        'current_password' =>
                            'CorrectPassword123!',
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone_number',
            ]);

        $fresh =
            $customer->fresh();

        $this->assertSame(
            'customer@example.com',
            $fresh->email
        );

        $this->assertSame(
            '+639171111111',
            $fresh->phone_number
        );
    }

    public function test_invalid_philippine_phone_number_is_rejected(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'phone_number' =>
                            '12345',

                        'current_password' =>
                            'password',
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone_number',
            ]);
    }

    public function test_client_cannot_inject_role_status_verification_password_or_identity_fields(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'customer@example.com',

                    'phone_number' =>
                        '+639171111111',
                ]);

        $originalPassword =
            $customer->password;

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                [
                    ...$this->profilePayload(
                        $customer
                    ),

                    'user_id' =>
                        999999,

                    'role' =>
                        'admin',

                    'status' =>
                        'suspended',

                    'phone_verified_at' =>
                        now()->toISOString(),

                    'email_verified_at' =>
                        null,

                    'password' =>
                        'InjectedPassword123!',
                ]
            )
            ->assertUnprocessable();

        $fresh =
            $customer->fresh();

        $this->assertSame(
            $customer->id,
            $fresh->id
        );

        $this->assertSame(
            UserRole::Customer,
            $fresh->role
        );

        $this->assertSame(
            UserStatus::Active,
            $fresh->status
        );

        $this->assertNotNull(
            $fresh->phone_verified_at
        );

        $this->assertNotNull(
            $fresh->email_verified_at
        );

        $this->assertSame(
            $originalPassword,
            $fresh->password
        );
    }

    public function test_profile_update_response_does_not_expose_password_tokens_or_raw_verification_timestamps(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'first_name' =>
                            'Updated',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonMissingPath(
                'data.user.password'
            )
            ->assertJsonMissingPath(
                'data.user.remember_token'
            )
            ->assertJsonMissingPath(
                'data.user.tokens'
            )
            ->assertJsonMissingPath(
                'data.user.phone_verified_at'
            )
            ->assertJsonMissingPath(
                'data.user.email_verified_at'
            );
    }

    public function test_name_only_change_does_not_revoke_other_sessions(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'customer@example.com',

                    'phone_number' =>
                        '+639171111111',
                ]);

        $currentToken =
            $customer->createToken(
                'current-device',
                [
                    'role:customer',
                ],
                now()->addDay()
            );

        $otherToken =
            $customer->createToken(
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

        $this->withToken(
            $currentToken
                ->plainTextToken
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'first_name' =>
                            'New Name',
                    ]
                )
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'id' =>
                    $currentTokenId,
            ]
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'id' =>
                    $otherTokenId,
            ]
        );
    }

    public function test_after_phone_change_customer_features_are_blocked_until_new_number_is_verified(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'customer@example.com',

                    'phone_number' =>
                        '+639171111111',

                    'password' =>
                        'CorrectPassword123!',
                ]);

        $currentToken =
            $customer->createToken(
                'current-device',
                [
                    'role:customer',
                ],
                now()->addDay()
            );

        /*
        |--------------------------------------------------------------------------
        | Change Verified Mobile Number
        |--------------------------------------------------------------------------
        */

        $this->withToken(
            $currentToken
                ->plainTextToken
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $customer,
                    [
                        'phone_number' =>
                            '+639172222222',

                        'current_password' =>
                            'CorrectPassword123!',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.phone_verified',
                false
            )
            ->assertJsonPath(
                'data.requires_phone_verification',
                true
            );

        /*
        |--------------------------------------------------------------------------
        | Confirm Database State
        |--------------------------------------------------------------------------
        */

        $freshCustomer =
            $customer->fresh();

        $this->assertNull(
            $freshCustomer
                ->phone_verified_at
        );

        $this->assertSame(
            '+639172222222',
            $freshCustomer
                ->phone_number
        );

        /*
        |--------------------------------------------------------------------------
        | Simulate A New HTTP Request
        |--------------------------------------------------------------------------
        |
        | The test authentication guard may retain the User instance from the
        | first request. Forget the guard so Sanctum reloads the current User
        | state from the database on the next request.
        |
        */

        $this->app[
            'auth'
        ]->forgetGuards();

        /*
        |--------------------------------------------------------------------------
        | Protected Customer Feature Must Now Be Blocked
        |--------------------------------------------------------------------------
        */

        $this->withToken(
            $currentToken
                ->plainTextToken
        )
            ->patchJson(
                '/api/v1/customer/profile',
                $this->profilePayload(
                    $freshCustomer
                )
            )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Please verify your phone number before using customer features.'
            );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function profilePayload(
        User $user,
        array $overrides = []
    ): array {
        return array_merge(
            [
                'first_name' =>
                    $user->first_name,

                'middle_name' =>
                    $user->middle_name,

                'last_name' =>
                    $user->last_name,

                'email' =>
                    $user->email,

                'phone_number' =>
                    $user->phone_number,
            ],
            $overrides
        );
    }
}