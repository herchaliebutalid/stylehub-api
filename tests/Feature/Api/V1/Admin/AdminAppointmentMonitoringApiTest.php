<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAppointmentMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_monitoring_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/appointments'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_appointment_monitoring(): void
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
                '/api/v1/admin/appointments'
            )
            ->assertForbidden();
    }

    public function test_shop_owner_cannot_access_admin_appointment_monitoring(): void
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
                '/api/v1/admin/appointments'
            )
            ->assertForbidden();
    }

    public function test_admin_can_browse_all_appointments(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Appointment::withoutEvents(
            fn () =>
                Appointment::factory()
                    ->count(2)
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/appointments'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                2,
                'data.appointments'
            )
            ->assertJsonPath(
                'data.pagination.total',
                2
            );
    }

    public function test_admin_can_filter_appointments_by_status(): void
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
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/appointments?status=confirmed'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.appointments'
            )
            ->assertJsonPath(
                'data.appointments.0.status',
                AppointmentStatus::Confirmed->value
            );
    }

    public function test_admin_can_filter_appointments_by_shop(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $first =
            Appointment::withoutEvents(
                fn () =>
                    Appointment::factory()
                        ->create()
            );

        Appointment::withoutEvents(
            fn () =>
                Appointment::factory()
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/appointments?shop_id='
                .$first->shop_id
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.appointments'
            )
            ->assertJsonPath(
                'data.appointments.0.shop.id',
                $first->shop_id
            );
    }

    public function test_admin_can_filter_appointments_by_customer(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        Appointment::withoutEvents(
            fn () =>
                Appointment::factory()
                    ->create([
                        'customer_id' =>
                            $customer->id,
                    ])
        );

        Appointment::withoutEvents(
            fn () =>
                Appointment::factory()
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/appointments?customer_id='
                .$customer->id
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.appointments'
            )
            ->assertJsonPath(
                'data.appointments.0.customer.id',
                $customer->id
            );
    }

    public function test_admin_can_filter_appointments_by_date_range(): void
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
                '/api/v1/admin/appointments'
                .'?date_from=2026-08-15'
                .'&date_to=2026-08-25'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.appointments'
            )
            ->assertJsonPath(
                'data.appointments.0.appointment_date',
                '2026-08-20'
            );
    }

    public function test_admin_can_search_appointments_by_customer(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create([
                    'first_name' =>
                        'UniqueMonitorName',

                    'last_name' =>
                        'Customer',
                ]);

        Appointment::withoutEvents(
            fn () =>
                Appointment::factory()
                    ->create([
                        'customer_id' =>
                            $customer->id,
                    ])
        );

        Appointment::withoutEvents(
            fn () =>
                Appointment::factory()
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/appointments'
                .'?search=UniqueMonitorName'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.appointments'
            )
            ->assertJsonPath(
                'data.appointments.0.customer.id',
                $customer->id
            );
    }

    public function test_admin_can_search_appointments_by_shop(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'name' =>
                        'Unique Appointment Shop',
                ]);

        $service =
            Service::factory()
                ->for(
                    $shop
                )
                ->create();

        Appointment::withoutEvents(
            fn () =>
                Appointment::factory()
                    ->create([
                        'shop_id' =>
                            $shop->id,

                        'service_id' =>
                            $service->id,
                    ])
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/appointments'
                .'?search=Unique%20Appointment%20Shop'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.appointments'
            )
            ->assertJsonPath(
                'data.appointments.0.shop.id',
                $shop->id
            );
    }

    public function test_admin_appointment_list_is_paginated(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Appointment::withoutEvents(
            fn () =>
                Appointment::factory()
                    ->count(3)
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/appointments?per_page=2'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.appointments'
            )
            ->assertJsonPath(
                'data.pagination.total',
                3
            )
            ->assertJsonPath(
                'data.pagination.per_page',
                2
            );
    }

    public function test_excessive_appointment_page_size_is_rejected(): void
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
                '/api/v1/admin/appointments?per_page=51'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_unknown_appointment_query_parameter_is_rejected(): void
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
                '/api/v1/admin/appointments?password=test'
            )
            ->assertUnprocessable();
    }

    public function test_invalid_appointment_date_range_is_rejected(): void
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
                '/api/v1/admin/appointments'
                .'?date_from=2026-08-25'
                .'&date_to=2026-08-10'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);
    }

    public function test_admin_can_view_one_appointment(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $appointment =
            Appointment::withoutEvents(
                fn () =>
                    Appointment::factory()
                        ->create()
            );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/appointments/{$appointment->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.id',
                $appointment->id
            )
            ->assertJsonPath(
                'data.appointment.customer.id',
                $appointment->customer_id
            )
            ->assertJsonPath(
                'data.appointment.shop.id',
                $appointment->shop_id
            )
            ->assertJsonStructure([
                'data' => [
                    'appointment' => [
                        'id',
                        'status',
                        'appointment_date',
                        'start_time',
                        'end_time',
                        'customer',
                        'shop',
                        'service',
                        'booking_snapshot',
                        'reschedule_requests_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_nonexistent_appointment_returns_not_found(): void
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
                '/api/v1/admin/appointments/999999'
            )
            ->assertNotFound();
    }

    public function test_admin_appointment_response_does_not_expose_passwords_or_tokens(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $appointment =
            Appointment::withoutEvents(
                fn () =>
                    Appointment::factory()
                        ->create()
            );

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )->getJson(
                "/api/v1/admin/appointments/{$appointment->id}"
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
    }
}