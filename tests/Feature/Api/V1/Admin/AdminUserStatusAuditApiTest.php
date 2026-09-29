<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserStatusAuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspending_customer_creates_admin_audit_log(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create([
                    'status' =>
                        UserStatus::Active,
                ]);

        $customer->createToken(
            'Customer Phone'
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/suspend"
            )
            ->assertOk();

        $auditLog =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::UserSuspended
                        ->value
                )
                ->where(
                    'actor_id',
                    $admin->id
                )
                ->where(
                    'auditable_type',
                    $customer
                        ->getMorphClass()
                )
                ->where(
                    'auditable_id',
                    $customer->id
                )
                ->firstOrFail();

        $this->assertSame(
            UserRole::Admin->value,
            $auditLog->actor_role
        );

        $this->assertSame(
            UserStatus::Active->value,
            $auditLog->metadata[
                'previous_status'
            ]
        );

        $this->assertSame(
            UserStatus::Suspended->value,
            $auditLog->metadata[
                'new_status'
            ]
        );

        $this->assertSame(
            1,
            $auditLog->metadata[
                'revoked_session_count'
            ]
        );
    }

    public function test_reactivating_shop_owner_creates_admin_audit_log(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->suspended()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$owner->id}/reactivate"
            )
            ->assertOk();

        $auditLog =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::UserReactivated
                        ->value
                )
                ->where(
                    'actor_id',
                    $admin->id
                )
                ->where(
                    'auditable_type',
                    $owner
                        ->getMorphClass()
                )
                ->where(
                    'auditable_id',
                    $owner->id
                )
                ->firstOrFail();

        $this->assertSame(
            UserRole::Admin->value,
            $auditLog->actor_role
        );

        $this->assertSame(
            UserStatus::Suspended->value,
            $auditLog->metadata[
                'previous_status'
            ]
        );

        $this->assertSame(
            UserStatus::Active->value,
            $auditLog->metadata[
                'new_status'
            ]
        );
    }

    public function test_failed_duplicate_suspend_does_not_create_second_audit_entry(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/suspend"
            )
            ->assertOk();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/suspend"
            )
            ->assertStatus(
                409
            );

        $this->assertSame(
            1,
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::UserSuspended
                        ->value
                )
                ->where(
                    'auditable_type',
                    $customer
                        ->getMorphClass()
                )
                ->where(
                    'auditable_id',
                    $customer->id
                )
                ->count()
        );
    }

    public function test_failed_duplicate_reactivation_does_not_create_audit_entry(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create([
                    'status' =>
                        UserStatus::Active,
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/reactivate"
            )
            ->assertStatus(
                409
            );

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'event' =>
                    AuditEvent::UserReactivated
                        ->value,

                'auditable_type' =>
                    $customer
                        ->getMorphClass(),

                'auditable_id' =>
                    $customer->id,
            ]
        );
    }

    public function test_admin_audit_api_exposes_user_as_safe_resource_type(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/suspend"
            )
            ->assertOk();

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/admin/audit-logs'
                    .'?resource_type=user'
                );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.audit_logs'
            )
            ->assertJsonPath(
                'data.audit_logs.0.event.value',
                AuditEvent::UserSuspended
                    ->value
            )
            ->assertJsonPath(
                'data.audit_logs.0.resource.type',
                'user'
            )
            ->assertJsonPath(
                'data.audit_logs.0.resource.type_label',
                'User'
            )
            ->assertJsonPath(
                'data.audit_logs.0.resource.id',
                $customer->id
            );

        $this->assertStringNotContainsString(
            'App\\\\Models\\\\User',
            $response->getContent()
        );
    }

    public function test_user_audit_log_never_contains_token_values(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $plainTextToken =
            $customer->createToken(
                'Secret Mobile Device'
            )->plainTextToken;

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/suspend"
            )
            ->assertOk();

        $auditLog =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::UserSuspended
                        ->value
                )
                ->where(
                    'auditable_type',
                    $customer
                        ->getMorphClass()
                )
                ->where(
                    'auditable_id',
                    $customer->id
                )
                ->firstOrFail();

        $encodedMetadata =
            json_encode(
                $auditLog->metadata,
                JSON_THROW_ON_ERROR
            );

        $this->assertStringNotContainsString(
            $plainTextToken,
            $encodedMetadata
        );

        $this->assertArrayNotHasKey(
            'token',
            $auditLog->metadata
        );

        $this->assertArrayNotHasKey(
            'access_token',
            $auditLog->metadata
        );

        $this->assertArrayNotHasKey(
            'plain_text_token',
            $auditLog->metadata
        );

        $this->assertArrayNotHasKey(
            'revoked_token_count',
            $auditLog->metadata
        );

        $this->assertArrayHasKey(
            'revoked_session_count',
            $auditLog->metadata
        );
    }
}