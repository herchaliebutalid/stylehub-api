<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\ShopApprovalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Promo;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/dashboard'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_dashboard(): void
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
                '/api/v1/admin/dashboard'
            )
            ->assertForbidden();
    }

    public function test_shop_owner_cannot_access_admin_dashboard(): void
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
                '/api/v1/admin/dashboard'
            )
            ->assertForbidden();
    }

    public function test_admin_can_access_dashboard(): void
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
                '/api/v1/admin/dashboard'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );
    }

    public function test_dashboard_returns_correct_user_counts(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        User::factory()
            ->customer()
            ->count(2)
            ->create([
                'status' =>
                    UserStatus::Active,
            ]);

        User::factory()
            ->customer()
            ->create([
                'status' =>
                    UserStatus::Suspended,
            ]);

        User::factory()
            ->shopOwner()
            ->create([
                'status' =>
                    UserStatus::Active,
            ]);

        User::factory()
            ->shopOwner()
            ->create([
                'status' =>
                    UserStatus::Suspended,
            ]);

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/admin/dashboard'
                );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.users.customers.total',
                3
            )
            ->assertJsonPath(
                'data.users.customers.active',
                2
            )
            ->assertJsonPath(
                'data.users.customers.suspended',
                1
            )
            ->assertJsonPath(
                'data.users.shop_owners.total',
                2
            )
            ->assertJsonPath(
                'data.users.shop_owners.active',
                1
            )
            ->assertJsonPath(
                'data.users.shop_owners.suspended',
                1
            );
    }

    public function test_dashboard_returns_correct_shop_counts(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Shop::withoutEvents(
            function (): void {
                Shop::factory()
                    ->create([
                        'approval_status' =>
                            ShopApprovalStatus::Pending,
                    ]);

                Shop::factory()
                    ->approved()
                    ->count(2)
                    ->create();

                Shop::factory()
                    ->create([
                        'approval_status' =>
                            ShopApprovalStatus::Rejected,
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/dashboard'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shops.total',
                4
            )
            ->assertJsonPath(
                'data.shops.pending',
                1
            )
            ->assertJsonPath(
                'data.shops.approved',
                2
            )
            ->assertJsonPath(
                'data.shops.rejected',
                1
            );
    }

    public function test_dashboard_returns_correct_appointment_counts(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Appointment::withoutEvents(
            function (): void {
                Appointment::factory()
                    ->create([
                        'status' =>
                            AppointmentStatus::Pending,
                    ]);

                Appointment::factory()
                    ->count(2)
                    ->create([
                        'status' =>
                            AppointmentStatus::Confirmed,
                    ]);

                Appointment::factory()
                    ->create([
                        'status' =>
                            AppointmentStatus::Completed,
                    ]);

                Appointment::factory()
                    ->create([
                        'status' =>
                            AppointmentStatus::Cancelled,
                    ]);

                Appointment::factory()
                    ->create([
                        'status' =>
                            AppointmentStatus::Declined,
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/dashboard'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointments.total',
                6
            )
            ->assertJsonPath(
                'data.appointments.pending',
                1
            )
            ->assertJsonPath(
                'data.appointments.confirmed',
                2
            )
            ->assertJsonPath(
                'data.appointments.completed',
                1
            )
            ->assertJsonPath(
                'data.appointments.cancelled',
                1
            )
            ->assertJsonPath(
                'data.appointments.declined',
                1
            );
    }

    public function test_dashboard_returns_other_operational_counts(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Promo::withoutEvents(
            function (): void {
                Promo::factory()
                    ->count(2)
                    ->create([
                        'is_active' =>
                            true,
                    ]);

                Promo::factory()
                    ->create([
                        'is_active' =>
                            false,
                    ]);
            }
        );

        Feedback::withoutEvents(
            fn () =>
                Feedback::factory()
                    ->count(2)
                    ->create()
        );

        AuditLog::factory()
            ->count(3)
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/dashboard'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.other.feedback',
                2
            )
            ->assertJsonPath(
                'data.other.active_promos',
                2
            )
            ->assertJsonPath(
                'data.other.audit_logs',
                3
            );
    }

    public function test_dashboard_does_not_expose_sensitive_user_information(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        User::factory()
            ->customer()
            ->create([
                'email' =>
                    'private-customer@example.com',

                'phone_number' =>
                    '09171234567',
            ]);

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )->getJson(
                '/api/v1/admin/dashboard'
            );

        $response->assertOk();

        $content =
            $response->getContent();

        $this->assertStringNotContainsString(
            'private-customer@example.com',
            $content
        );

        $this->assertStringNotContainsString(
            '09171234567',
            $content
        );

        $this->assertStringNotContainsString(
            'password',
            strtolower(
                $content
            )
        );

        $this->assertStringNotContainsString(
            'remember_token',
            strtolower(
                $content
            )
        );
    }

    public function test_dashboard_counts_only_customer_and_shop_owner_roles_in_user_sections(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        User::factory()
            ->customer()
            ->create();

        User::factory()
            ->shopOwner()
            ->create();

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )->getJson(
                '/api/v1/admin/dashboard'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.users.customers.total',
                1
            )
            ->assertJsonPath(
                'data.users.shop_owners.total',
                1
            );

        /*
         * Administrator count is deliberately not exposed as
         * part of the public operational dashboard payload.
         */
        $response->assertJsonMissingPath(
            'data.users.admins'
        );
    }
}