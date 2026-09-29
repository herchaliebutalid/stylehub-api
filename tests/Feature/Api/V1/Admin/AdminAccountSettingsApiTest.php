<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_account_settings_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/account'
        )->assertUnauthorized();

        $this->putJson(
            '/api/v1/admin/account/profile',
            $this->profilePayload()
        )->assertUnauthorized();

        $this->postJson(
            '/api/v1/admin/account/password',
            $this->passwordPayload()
        )->assertUnauthorized();
    }

    public function test_customer_and_shop_owner_cannot_access_admin_account_settings(): void
    {
        foreach (
            [
                User::factory()
                    ->customer()
                    ->create(),

                User::factory()
                    ->shopOwner()
                    ->create(),
            ] as $user
        ) {
            $this->actingAs(
                $user,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/admin/account'
                )
                ->assertForbidden();
        }
    }

    public function test_suspended_admin_cannot_access_account_settings(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->suspended()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/account'
            )
            ->assertForbidden();
    }

    public function test_admin_can_view_only_safe_own_account_information(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'first_name' =>
                        'System',

                    'last_name' =>
                        'Administrator',

                    'email' =>
                        'admin@example.com',

                    'phone_number' =>
                        '+639171234567',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/account'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.user.email',
                'admin@example.com'
            )
            ->assertJsonPath(
                'data.user.phone_number',
                '+639171234567'
            )
            ->assertJsonPath(
                'data.user.role',
                UserRole::Admin->value
            )
            ->assertJsonPath(
                'data.user.status',
                UserStatus::Active->value
            )
            ->assertJsonMissingPath(
                'data.user.password'
            )
            ->assertJsonMissingPath(
                'data.user.remember_token'
            )
            ->assertJsonMissingPath(
                'data.user.tokens'
            );
    }

    public function test_admin_can_update_own_name_and_email_with_normalization(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'email' =>
                        'old-admin@example.com',

                    'phone_number' =>
                        '+639171234567',

                    'email_verified_at' =>
                        now(),
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/account/profile',
                [
                    'first_name' =>
                        '  Style   Hub  ',

                    'middle_name' =>
                        '   ',

                    'last_name' =>
                        '  Administrator  ',

                    'email' =>
                        '  ADMIN.SETTINGS@EXAMPLE.COM  ',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.first_name',
                'Style Hub'
            )
            ->assertJsonPath(
                'data.user.middle_name',
                null
            )
            ->assertJsonPath(
                'data.user.last_name',
                'Administrator'
            )
            ->assertJsonPath(
                'data.user.email',
                'admin.settings@example.com'
            )
            ->assertJsonPath(
                'data.user.phone_number',
                '+639171234567'
            );

        $freshAdmin =
            $admin->fresh();

        $this->assertSame(
            'Style Hub',
            $freshAdmin->first_name
        );

        $this->assertNull(
            $freshAdmin->middle_name
        );

        $this->assertSame(
            'Administrator',
            $freshAdmin->last_name
        );

        $this->assertSame(
            'admin.settings@example.com',
            $freshAdmin->email
        );

        $this->assertSame(
            '+639171234567',
            $freshAdmin->phone_number
        );

        $this->assertSame(
            UserRole::Admin,
            $freshAdmin->role
        );

        $this->assertSame(
            UserStatus::Active,
            $freshAdmin->status
        );

        $this->assertNull(
            $freshAdmin->email_verified_at
        );
    }

    public function test_admin_profile_rejects_phone_role_status_and_other_server_controlled_fields(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'phone_number' =>
                        '+639171234567',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/account/profile',
                array_merge(
                    $this->profilePayload(),
                    [
                        'phone_number' =>
                            '+639189999999',

                        'role' =>
                            UserRole::Customer->value,

                        'status' =>
                            UserStatus::Suspended->value,

                        'email_verified_at' =>
                            now()->toISOString(),
                    ]
                )
            )
            ->assertUnprocessable();

        $freshAdmin =
            $admin->fresh();

        $this->assertSame(
            '+639171234567',
            $freshAdmin->phone_number
        );

        $this->assertSame(
            UserRole::Admin,
            $freshAdmin->role
        );

        $this->assertSame(
            UserStatus::Active,
            $freshAdmin->status
        );
    }

    public function test_admin_profile_rejects_email_used_by_another_account(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'email' =>
                        'admin@example.com',
                ]);

        User::factory()
            ->customer()
            ->create([
                'email' =>
                    'existing@example.com',
            ]);

        $payload =
            $this->profilePayload();

        $payload['email'] =
            'existing@example.com';

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/account/profile',
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertSame(
            'admin@example.com',
            $admin->fresh()->email
        );
    }

    public function test_account_settings_update_only_the_authenticated_admin(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'email' =>
                        'current-admin@example.com',
                ]);

        $otherAdmin =
            User::factory()
                ->admin()
                ->create([
                    'first_name' =>
                        'Other',

                    'email' =>
                        'other-admin@example.com',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/account/profile',
                [
                    'first_name' =>
                        'Updated',

                    'middle_name' =>
                        null,

                    'last_name' =>
                        'Administrator',

                    'email' =>
                        'updated-admin@example.com',
                ]
            )
            ->assertOk();

        $this->assertSame(
            'Updated',
            $admin->fresh()->first_name
        );

        $this->assertSame(
            'Other',
            $otherAdmin->fresh()->first_name
        );

        $this->assertSame(
            'other-admin@example.com',
            $otherAdmin->fresh()->email
        );
    }

    public function test_admin_profile_update_is_audited_without_storing_profile_values(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'first_name' =>
                        'Original',

                    'email' =>
                        'original@example.com',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/account/profile',
                [
                    'first_name' =>
                        'Updated',

                    'middle_name' =>
                        null,

                    'last_name' =>
                        $admin->last_name,

                    'email' =>
                        'updated@example.com',
                ]
            )
            ->assertOk();

        $log =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::AdminAccountUpdated
                        ->value
                )
                ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $log->actor_id
        );

        $this->assertSame(
            $admin->id,
            $log->auditable_id
        );

        $this->assertSame(
            'admin',
            $log->actor_role
        );

        $this->assertSame(
            'admin_account_settings',
            $log->metadata['source']
        );

        $this->assertContains(
            'first_name',
            $log->metadata[
                'changed_fields'
            ]
        );

        $this->assertContains(
            'email',
            $log->metadata[
                'changed_fields'
            ]
        );

        $this->assertTrue(
            $log->metadata[
                'email_verification_reset'
            ]
        );

        $this->assertArrayNotHasKey(
            'old_email',
            $log->metadata
        );

        $this->assertArrayNotHasKey(
            'new_email',
            $log->metadata
        );
    }

    public function test_admin_password_change_rejects_incorrect_current_password(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'password' =>
                        'OldSecurePassword123!',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                '/api/v1/admin/account/password',
                [
                    'current_password' =>
                        'WrongPassword123!',

                    'password' =>
                        'NewSecurePassword456!',

                    'password_confirmation' =>
                        'NewSecurePassword456!',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);

        $this->assertTrue(
            Hash::check(
                'OldSecurePassword123!',
                $admin->fresh()->password
            )
        );

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'event' =>
                    AuditEvent::AdminPasswordChanged
                        ->value,
            ]
        );
    }

    public function test_admin_password_change_keeps_current_session_revokes_other_sessions_and_is_audited(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'password' =>
                        'OldSecurePassword123!',
                ]);

        $currentToken =
            $admin->createToken(
                'stylehub-admin-web',
                ['*'],
                now()->addDay()
            );

        $otherToken =
            $admin->createToken(
                'other-admin-device',
                ['*'],
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
            ->postJson(
                '/api/v1/admin/account/password',
                $this->passwordPayload()
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );

        $this->assertTrue(
            Hash::check(
                'NewSecurePassword456!',
                $admin->fresh()->password
            )
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'id' =>
                    $currentTokenId,

                'tokenable_id' =>
                    $admin->id,
            ]
        );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'id' =>
                    $otherTokenId,
            ]
        );

        $log =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::AdminPasswordChanged
                        ->value
                )
                ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $log->actor_id
        );

        $this->assertSame(
            $admin->id,
            $log->auditable_id
        );

        $this->assertSame(
            'admin_account_settings',
            $log->metadata['source']
        );

        $this->assertTrue(
            $log->metadata[
                'other_sessions_revoked'
            ]
        );
    }

    public function test_admin_password_endpoint_rejects_client_controlled_identity_fields(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'password' =>
                        'OldSecurePassword123!',
                ]);

        $otherAdmin =
            User::factory()
                ->admin()
                ->create([
                    'password' =>
                        'OtherPassword123!',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                '/api/v1/admin/account/password',
                array_merge(
                    $this->passwordPayload(),
                    [
                        'user_id' =>
                            $otherAdmin->id,
                    ]
                )
            )
            ->assertUnprocessable();

        $this->assertTrue(
            Hash::check(
                'OtherPassword123!',
                $otherAdmin->fresh()->password
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(): array
    {
        return [
            'first_name' =>
                'StyleHub',

            'middle_name' =>
                null,

            'last_name' =>
                'Administrator',

            'email' =>
                'admin.settings@example.com',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function passwordPayload(): array
    {
        return [
            'current_password' =>
                'OldSecurePassword123!',

            'password' =>
                'NewSecurePassword456!',

            'password_confirmation' =>
                'NewSecurePassword456!',
        ];
    }
}