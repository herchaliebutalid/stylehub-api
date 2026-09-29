<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPerformanceAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_exposes_no_show_analytics_with_consistent_attendance_rate(): void
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
                        'Attendance Shop',
                ]);

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        'Attendance Service',
                ]);

        foreach (
            [
                AppointmentStatus::Completed,
                AppointmentStatus::Completed,
                AppointmentStatus::Completed,
                AppointmentStatus::NoShow,
                AppointmentStatus::Cancelled,
                AppointmentStatus::Cancelled,
                AppointmentStatus::Pending,
            ] as $status
        ) {
            $this->createAppointment(
                shop:
                    $shop,

                service:
                    $service,

                status:
                    $status,
            );
        }

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
                7
            )
            ->assertJsonPath(
                'data.activity.appointments.completed',
                3
            )
            ->assertJsonPath(
                'data.activity.appointments.no_show',
                1
            )
            ->assertJsonPath(
                'data.analytics.no_show.total_bookings',
                7
            )
            ->assertJsonPath(
                'data.analytics.no_show.attendance_outcomes',
                4
            )
            ->assertJsonPath(
                'data.analytics.no_show.completed',
                3
            )
            ->assertJsonPath(
                'data.analytics.no_show.no_show',
                1
            )
            ->assertJsonPath(
                'data.analytics.no_show.completion_rate',
                75
            )
            ->assertJsonPath(
                'data.analytics.no_show.no_show_rate',
                25
            );
    }

    public function test_top_shops_rank_by_booking_volume_and_include_performance_metrics(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $alphaShop =
            Shop::factory()
                ->approved()
                ->create([
                    'name' =>
                        'Alpha Studio',
                ]);

        $betaShop =
            Shop::factory()
                ->approved()
                ->create([
                    'name' =>
                        'Beta Studio',
                ]);

        $alphaService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $alphaShop->id,

                    'name' =>
                        'Alpha Service',
                ]);

        $betaService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $betaShop->id,

                    'name' =>
                        'Beta Service',
                ]);

        $this->createAppointment(
            $alphaShop,
            $alphaService,
            AppointmentStatus::Completed,
            '300.00'
        );

        $this->createAppointment(
            $alphaShop,
            $alphaService,
            AppointmentStatus::Completed,
            '300.00'
        );

        $this->createAppointment(
            $alphaShop,
            $alphaService,
            AppointmentStatus::NoShow,
            '300.00'
        );

        $this->createAppointment(
            $alphaShop,
            $alphaService,
            AppointmentStatus::Cancelled,
            '300.00'
        );

        for (
            $index = 0;
            $index < 3;
            $index++
        ) {
            $this->createAppointment(
                $betaShop,
                $betaService,
                AppointmentStatus::Completed,
                '500.00'
            );
        }

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/reports/overview'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.analytics.top_shops.0.shop_id',
                $alphaShop->id
            )
            ->assertJsonPath(
                'data.analytics.top_shops.0.shop_name',
                'Alpha Studio'
            )
            ->assertJsonPath(
                'data.analytics.top_shops.0.total_bookings',
                4
            )
            ->assertJsonPath(
                'data.analytics.top_shops.0.completed_bookings',
                2
            )
            ->assertJsonPath(
                'data.analytics.top_shops.0.no_show_count',
                1
            )
            ->assertJsonPath(
                'data.analytics.top_shops.0.attendance_outcomes',
                3
            )
            ->assertJsonPath(
                'data.analytics.top_shops.0.no_show_rate',
                33.33
            )
            ->assertJsonPath(
                'data.analytics.top_shops.0.completed_revenue',
                600
            )
            ->assertJsonPath(
                'data.analytics.top_shops.1.shop_id',
                $betaShop->id
            )
            ->assertJsonPath(
                'data.analytics.top_shops.1.total_bookings',
                3
            )
            ->assertJsonPath(
                'data.analytics.top_shops.1.completed_revenue',
                1500
            );
    }

    public function test_top_services_rank_across_platform_and_identify_parent_shop(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $firstShop =
            Shop::factory()
                ->approved()
                ->create([
                    'name' =>
                        'First Shop',
                ]);

        $secondShop =
            Shop::factory()
                ->approved()
                ->create([
                    'name' =>
                        'Second Shop',
                ]);

        $popularService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $firstShop->id,

                    'name' =>
                        'Popular Haircut',
                ]);

        $premiumService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $secondShop->id,

                    'name' =>
                        'Premium Treatment',
                ]);

        $popularStatuses = [
            AppointmentStatus::Completed,
            AppointmentStatus::Completed,
            AppointmentStatus::NoShow,
            AppointmentStatus::Cancelled,
        ];

        foreach (
            $popularStatuses as $status
        ) {
            $this->createAppointment(
                $firstShop,
                $popularService,
                $status,
                '250.00'
            );
        }

        for (
            $index = 0;
            $index < 3;
            $index++
        ) {
            $this->createAppointment(
                $secondShop,
                $premiumService,
                AppointmentStatus::Completed,
                '700.00'
            );
        }

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/reports/overview'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.analytics.top_services.0.service_id',
                $popularService->id
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.service_name',
                'Popular Haircut'
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.shop_id',
                $firstShop->id
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.shop_name',
                'First Shop'
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.total_bookings',
                4
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.completed_bookings',
                2
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.no_show_count',
                1
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.no_show_rate',
                33.33
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.completed_revenue',
                500
            )
            ->assertJsonPath(
                'data.analytics.top_services.1.service_id',
                $premiumService->id
            );
    }

    public function test_performance_analytics_respect_report_appointment_date_range(): void
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
                        'Period Shop',
                ]);

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        'Period Service',
                ]);

        $this->createAppointment(
            $shop,
            $service,
            AppointmentStatus::NoShow,
            '300.00',
            '2026-08-10'
        );

        $this->createAppointment(
            $shop,
            $service,
            AppointmentStatus::Completed,
            '300.00',
            '2026-08-20'
        );

        $this->createAppointment(
            $shop,
            $service,
            AppointmentStatus::NoShow,
            '300.00',
            '2026-08-20'
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
                'data.activity.appointments.total',
                2
            )
            ->assertJsonPath(
                'data.activity.appointments.no_show',
                1
            )
            ->assertJsonPath(
                'data.analytics.no_show.attendance_outcomes',
                2
            )
            ->assertJsonPath(
                'data.analytics.no_show.no_show_rate',
                50
            )
            ->assertJsonPath(
                'data.analytics.top_shops.0.total_bookings',
                2
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.total_bookings',
                2
            );
    }

    public function test_no_show_rates_are_null_when_no_attendance_outcomes_exist(): void
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
                        'Future Shop',
                ]);

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        'Future Service',
                ]);

        $this->createAppointment(
            $shop,
            $service,
            AppointmentStatus::Pending
        );

        $this->createAppointment(
            $shop,
            $service,
            AppointmentStatus::Cancelled
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
                'data.analytics.no_show.attendance_outcomes',
                0
            )
            ->assertJsonPath(
                'data.analytics.no_show.completion_rate',
                null
            )
            ->assertJsonPath(
                'data.analytics.no_show.no_show_rate',
                null
            )
            ->assertJsonPath(
                'data.analytics.top_shops.0.no_show_rate',
                null
            )
            ->assertJsonPath(
                'data.analytics.top_services.0.no_show_rate',
                null
            );
    }

    public function test_top_rankings_are_limited_to_five_rows(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        for (
            $index = 1;
            $index <= 6;
            $index++
        ) {
            $shop =
                Shop::factory()
                    ->approved()
                    ->create([
                        'name' =>
                            "Shop {$index}",
                    ]);

            $service =
                Service::factory()
                    ->create([
                        'shop_id' =>
                            $shop->id,

                        'name' =>
                            "Service {$index}",
                    ]);

            $this->createAppointment(
                $shop,
                $service,
                AppointmentStatus::Completed,
                (string) (
                    100 * $index
                )
            );
        }

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/reports/overview'
            )
            ->assertOk()
            ->assertJsonCount(
                5,
                'data.analytics.top_shops'
            )
            ->assertJsonCount(
                5,
                'data.analytics.top_services'
            );
    }

    private function createAppointment(
        Shop $shop,
        Service $service,
        AppointmentStatus $status,
        string $price = '250.00',
        ?string $appointmentDate = null
    ): Appointment {
        return Appointment::withoutEvents(
            fn (): Appointment =>
                Appointment::factory()
                    ->create([
                        'shop_id' =>
                            $shop->id,

                        'service_id' =>
                            $service->id,

                        'service_name' =>
                            $service->name,

                        'price_snapshot' =>
                            $price,

                        'status' =>
                            $status,

                        'appointment_date' =>
                            $appointmentDate
                            ?? now()
                                ->addDay()
                                ->toDateString(),
                    ])
        );
    }
}