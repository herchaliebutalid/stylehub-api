<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\RescheduleRequestStatus;
use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\RescheduleRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/reports/overview'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_reports(): void
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
                '/api/v1/admin/reports/overview'
            )
            ->assertForbidden();
    }

    public function test_shop_owner_cannot_access_admin_reports(): void
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
                '/api/v1/admin/reports/overview'
            )
            ->assertForbidden();
    }

    public function test_admin_can_access_report_overview(): void
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
                '/api/v1/admin/reports/overview'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.period.is_all_time',
                true
            )
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'current_state' => [
                        'users',
                        'shops',
                    ],
                    'activity' => [
                        'appointments',
                        'feedback',
                        'reschedule_requests',
                    ],
                ],
                'generated_at',
            ]);
    }

    public function test_report_counts_only_customers_and_shop_owners_in_user_statistics(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        User::factory()
            ->customer()
            ->count(2)
            ->create();

        User::factory()
            ->customer()
            ->suspended()
            ->create();

        User::factory()
            ->shopOwner()
            ->create();

        User::factory()
            ->shopOwner()
            ->suspended()
            ->create();

        User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/reports/overview'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.current_state.users.customers.total',
                3
            )
            ->assertJsonPath(
                'data.current_state.users.customers.active',
                2
            )
            ->assertJsonPath(
                'data.current_state.users.customers.suspended',
                1
            )
            ->assertJsonPath(
                'data.current_state.users.shop_owners.total',
                2
            )
            ->assertJsonPath(
                'data.current_state.users.shop_owners.active',
                1
            )
            ->assertJsonPath(
                'data.current_state.users.shop_owners.suspended',
                1
            );
    }

    public function test_report_returns_current_shop_statistics(): void
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

                        'availability_status' =>
                            ShopAvailabilityStatus::Closed,
                    ]);

                Shop::factory()
                    ->approved()
                    ->create([
                        'availability_status' =>
                            ShopAvailabilityStatus::Open,
                    ]);

                Shop::factory()
                    ->approved()
                    ->create([
                        'availability_status' =>
                            ShopAvailabilityStatus::FullyBooked,
                    ]);

                Shop::factory()
                    ->create([
                        'approval_status' =>
                            ShopApprovalStatus::Rejected,

                        'availability_status' =>
                            ShopAvailabilityStatus::AcceptingWalkIns,
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/reports/overview'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.current_state.shops.total',
                4
            )
            ->assertJsonPath(
                'data.current_state.shops.approval.pending',
                1
            )
            ->assertJsonPath(
                'data.current_state.shops.approval.approved',
                2
            )
            ->assertJsonPath(
                'data.current_state.shops.approval.rejected',
                1
            )
            ->assertJsonPath(
                'data.current_state.shops.availability.open',
                1
            )
            ->assertJsonPath(
                'data.current_state.shops.availability.closed',
                1
            )
            ->assertJsonPath(
                'data.current_state.shops.availability.fully_booked',
                1
            )
            ->assertJsonPath(
                'data.current_state.shops.availability.accepting_walk_ins',
                1
            );
    }

    public function test_report_returns_appointment_status_statistics(): void
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
                    ->create([
                        'status' =>
                            AppointmentStatus::Confirmed,
                    ]);

                Appointment::factory()
                    ->count(2)
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
                '/api/v1/admin/reports/overview'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.activity.appointments.total',
                6
            )
            ->assertJsonPath(
                'data.activity.appointments.pending',
                1
            )
            ->assertJsonPath(
                'data.activity.appointments.confirmed',
                1
            )
            ->assertJsonPath(
                'data.activity.appointments.completed',
                2
            )
            ->assertJsonPath(
                'data.activity.appointments.cancelled',
                1
            )
            ->assertJsonPath(
                'data.activity.appointments.declined',
                1
            );
    }

    public function test_report_date_range_filters_appointments_by_appointment_date(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Appointment::withoutEvents(
            function (): void {
                Appointment::factory()
                    ->create([
                        'appointment_date' =>
                            '2026-08-10',
                    ]);

                Appointment::factory()
                    ->create([
                        'appointment_date' =>
                            '2026-08-20',
                    ]);

                Appointment::factory()
                    ->create([
                        'appointment_date' =>
                            '2026-08-30',
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/reports/overview'
                .'?date_from=2026-08-15'
                .'&date_to=2026-08-25'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.period.date_from',
                '2026-08-15'
            )
            ->assertJsonPath(
                'data.period.date_to',
                '2026-08-25'
            )
            ->assertJsonPath(
                'data.period.is_all_time',
                false
            )
            ->assertJsonPath(
                'data.activity.appointments.total',
                1
            );
    }

    public function test_report_returns_feedback_average_and_distribution(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Feedback::withoutEvents(
            function (): void {
                Feedback::factory()
                    ->create([
                        'rating' =>
                            5,
                    ]);

                Feedback::factory()
                    ->create([
                        'rating' =>
                            5,
                    ]);

                Feedback::factory()
                    ->create([
                        'rating' =>
                            3,
                    ]);

                Feedback::factory()
                    ->create([
                        'rating' =>
                            1,
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/reports/overview'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.activity.feedback.total',
                4
            )
            ->assertJsonPath(
                'data.activity.feedback.average_rating',
                3.5
            )
            ->assertJsonPath(
                'data.activity.feedback.rating_distribution.1',
                1
            )
            ->assertJsonPath(
                'data.activity.feedback.rating_distribution.2',
                0
            )
            ->assertJsonPath(
                'data.activity.feedback.rating_distribution.3',
                1
            )
            ->assertJsonPath(
                'data.activity.feedback.rating_distribution.4',
                0
            )
            ->assertJsonPath(
                'data.activity.feedback.rating_distribution.5',
                2
            );
    }

    public function test_report_date_range_filters_feedback_by_submission_date(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Feedback::withoutEvents(
            function (): void {
                Feedback::factory()
                    ->create([
                        'created_at' =>
                            '2026-08-10 10:00:00',
                    ]);

                Feedback::factory()
                    ->create([
                        'created_at' =>
                            '2026-08-20 10:00:00',
                    ]);

                Feedback::factory()
                    ->create([
                        'created_at' =>
                            '2026-08-30 10:00:00',
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/reports/overview'
                .'?date_from=2026-08-15'
                .'&date_to=2026-08-25'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.activity.feedback.total',
                1
            );
    }

    public function test_report_returns_reschedule_request_statistics(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        RescheduleRequest::withoutEvents(
            function (): void {
                RescheduleRequest::factory()
                    ->create([
                        'status' =>
                            RescheduleRequestStatus::Pending,
                    ]);

                RescheduleRequest::factory()
                    ->create([
                        'status' =>
                            RescheduleRequestStatus::Approved,
                    ]);

                RescheduleRequest::factory()
                    ->count(2)
                    ->create([
                        'status' =>
                            RescheduleRequestStatus::Rejected,
                    ]);

                RescheduleRequest::factory()
                    ->create([
                        'status' =>
                            RescheduleRequestStatus::Withdrawn,
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/reports/overview'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.activity.reschedule_requests.total',
                5
            )
            ->assertJsonPath(
                'data.activity.reschedule_requests.pending',
                1
            )
            ->assertJsonPath(
                'data.activity.reschedule_requests.approved',
                1
            )
            ->assertJsonPath(
                'data.activity.reschedule_requests.rejected',
                2
            )
            ->assertJsonPath(
                'data.activity.reschedule_requests.withdrawn',
                1
            );
    }

    public function test_invalid_report_date_range_is_rejected(): void
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
                '/api/v1/admin/reports/overview'
                .'?date_from=2026-08-25'
                .'&date_to=2026-08-10'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);
    }

    public function test_report_period_longer_than_366_days_is_rejected(): void
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
                '/api/v1/admin/reports/overview'
                .'?date_from=2025-01-01'
                .'&date_to=2026-08-21'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);
    }

    public function test_unknown_report_query_parameter_is_rejected(): void
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
                '/api/v1/admin/reports/overview?password=test'
            )
            ->assertUnprocessable();
    }

    public function test_report_endpoint_is_read_only(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                '/api/v1/admin/reports/overview'
            )
            ->assertStatus(
                405
            );
    }

    public function test_report_response_does_not_expose_credentials(): void
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
                '/api/v1/admin/reports/overview'
            );

        $response->assertOk();

        $content =
            strtolower(
                $response->getContent()
            );

        $this->assertStringNotContainsString(
            '"password"',
            $content
        );

        $this->assertStringNotContainsString(
            'remember_token',
            $content
        );

        $this->assertStringNotContainsString(
            'plain_text_token',
            $content
        );

        $this->assertStringNotContainsString(
            'access_token',
            $content
        );

        $this->assertStringNotContainsString(
            'api_key',
            $content
        );
    }
}