<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/audit-logs'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_access_global_audit_logs(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs'
            )
            ->assertForbidden();
    }

    public function test_shop_owner_cannot_access_global_audit_logs(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs'
            )
            ->assertForbidden();
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        AuditLog::factory()
            ->count(3)
            ->create([
                'actor_id' =>
                    $admin->id,

                'actor_role' =>
                    UserRole::Admin
                        ->value,

                'event' =>
                    AuditEvent::ShopApproved,

                'auditable_type' =>
                    $shop->getMorphClass(),

                'auditable_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                3,
                'data.audit_logs'
            )
            ->assertJsonPath(
                'data.pagination.total',
                3
            );
    }

    public function test_admin_can_view_one_audit_log(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $log =
            AuditLog::factory()
                ->create([
                    'actor_id' =>
                        $admin->id,

                    'actor_role' =>
                        UserRole::Admin
                            ->value,

                    'event' =>
                        AuditEvent::ShopApproved,

                    'auditable_type' =>
                        $shop->getMorphClass(),

                    'auditable_id' =>
                        $shop->id,

                    'metadata' => [
                        'previous_status' =>
                            'pending',

                        'new_status' =>
                            'approved',
                    ],
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/audit-logs/{$log->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.audit_log.id',
                $log->id
            )
            ->assertJsonPath(
                'data.audit_log.event.value',
                AuditEvent::ShopApproved
                    ->value
            )
            ->assertJsonPath(
                'data.audit_log.event.label',
                'Shop Approved'
            )
            ->assertJsonPath(
                'data.audit_log.actor.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.audit_log.resource.type',
                'shop'
            )
            ->assertJsonPath(
                'data.audit_log.resource.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.audit_log.metadata.new_status',
                'approved'
            );
    }

    public function test_nonexistent_audit_log_returns_not_found(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs/999999'
            )
            ->assertNotFound();
    }

    public function test_admin_can_filter_by_event(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        AuditLog::factory()
            ->create([
                'event' =>
                    AuditEvent::ShopApproved,
            ]);

        AuditLog::factory()
            ->create([
                'event' =>
                    AuditEvent::ShopRejected,
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs?event=shop_approved'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.audit_logs'
            )
            ->assertJsonPath(
                'data.audit_logs.0.event.value',
                'shop_approved'
            );
    }

    public function test_admin_can_filter_by_actor(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $otherAdmin =
            User::factory()
                ->admin()
                ->create();

        AuditLog::factory()
            ->create([
                'actor_id' =>
                    $admin->id,

                'actor_role' =>
                    UserRole::Admin
                        ->value,
            ]);

        AuditLog::factory()
            ->create([
                'actor_id' =>
                    $otherAdmin->id,

                'actor_role' =>
                    UserRole::Admin
                        ->value,
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs?actor_id='
                .$otherAdmin->id
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.audit_logs'
            )
            ->assertJsonPath(
                'data.audit_logs.0.actor.id',
                $otherAdmin->id
            );
    }

    public function test_admin_can_filter_by_actor_role(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        AuditLog::factory()
            ->create([
                'actor_id' =>
                    $admin->id,

                'actor_role' =>
                    UserRole::Admin
                        ->value,
            ]);

        AuditLog::factory()
            ->create([
                'actor_id' =>
                    $customer->id,

                'actor_role' =>
                    UserRole::Customer
                        ->value,

                'event' =>
                    AuditEvent::AppointmentBooked,
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs?actor_role=customer'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.audit_logs'
            )
            ->assertJsonPath(
                'data.audit_logs.0.actor.role',
                'customer'
            );
    }

    public function test_admin_can_filter_by_resource_type(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $appointment =
            Appointment::factory()
                ->create();

        AuditLog::factory()
            ->create([
                'auditable_type' =>
                    $shop->getMorphClass(),

                'auditable_id' =>
                    $shop->id,
            ]);

        AuditLog::factory()
            ->create([
                'event' =>
                    AuditEvent::AppointmentBooked,

                'auditable_type' =>
                    $appointment
                        ->getMorphClass(),

                'auditable_id' =>
                    $appointment->id,
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs?resource_type=appointment'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.audit_logs'
            )
            ->assertJsonPath(
                'data.audit_logs.0.resource.type',
                'appointment'
            );
    }

    public function test_admin_can_filter_by_date_range(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        AuditLog::factory()
            ->create([
                'created_at' =>
                    '2026-08-01 10:00:00',
            ]);

        AuditLog::factory()
            ->create([
                'created_at' =>
                    '2026-08-15 10:00:00',
            ]);

        AuditLog::factory()
            ->create([
                'created_at' =>
                    '2026-09-01 10:00:00',
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs'
                .'?date_from=2026-08-10'
                .'&date_to=2026-08-31'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.audit_logs'
            );
    }

    public function test_invalid_event_filter_is_rejected(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs?event=delete_everything'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'event',
            ]);
    }

    public function test_unknown_query_parameter_is_rejected(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs?password=test'
            )
            ->assertUnprocessable();
    }

    public function test_excessive_page_size_is_rejected(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs?per_page=51'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_raw_internal_model_class_is_not_exposed(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        AuditLog::factory()
            ->create([
                'auditable_type' =>
                    $shop->getMorphClass(),

                'auditable_id' =>
                    $shop->id,
            ]);

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )->getJson(
                '/api/v1/admin/audit-logs'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.audit_logs.0.resource.type',
                'shop'
            )
            ->assertJsonMissingPath(
                'data.audit_logs.0.auditable_type'
            );

        $this->assertStringNotContainsString(
            'App\\\\Models\\\\Shop',
            $response->getContent()
        );
    }
}