<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOwnerAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' =>
                'Asia/Manila',
        ]);

        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                year:
                    2026,

                month:
                    9,

                day:
                    11,

                hour:
                    12,

                minute:
                    0,

                second:
                    0,

                timezone:
                    'Asia/Manila'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_analytics_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/analytics'
        )->assertUnauthorized();
    }

    public function test_customer_and_admin_cannot_use_shop_owner_analytics(): void
    {
        foreach (
            [
                User::factory()
                    ->customer()
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
                ->getJson(
                    '/api/v1/shop-owner/analytics'
                )
                ->assertForbidden();
        }
    }

    public function test_pending_shop_owner_cannot_use_analytics(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()
            ->create([
                'owner_id' =>
                    $owner->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/analytics'
            )
            ->assertForbidden();
    }

    public function test_default_period_is_last_thirty_days_and_only_uses_owned_shop(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        [, $otherShop, $otherService] =
            $this->approvedOwnerShopAndService();

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            date:
                '2026-08-13',

            status:
                AppointmentStatus::Pending
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            date:
                '2026-08-12',

            status:
                AppointmentStatus::Completed
        );

        $this->appointment(
            shop:
                $otherShop,

            service:
                $otherService,

            date:
                '2026-09-01',

            status:
                AppointmentStatus::Completed
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/analytics'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.period.date_from',
                '2026-08-13'
            )
            ->assertJsonPath(
                'data.period.date_to',
                '2026-09-11'
            )
            ->assertJsonPath(
                'data.period.days',
                30
            )
            ->assertJsonPath(
                'data.period.is_default',
                true
            )
            ->assertJsonPath(
                'data.summary.total_bookings',
                1
            )
            ->assertJsonPath(
                'data.status_counts.pending',
                1
            )
            ->assertJsonPath(
                'data.status_counts.completed',
                0
            );
    }

    public function test_custom_period_requires_complete_valid_bounded_range_and_rejects_unknown_fields(): void
    {
        [$owner] =
            $this->approvedOwnerShopAndService();

        $this->actingAs(
            $owner,
            'sanctum'
        );

        $this->getJson(
            '/api/v1/shop-owner/analytics?date_from=2026-09-01'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);

        $this->getJson(
            '/api/v1/shop-owner/analytics?date_from=2026-09-10&date_to=2026-09-01'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);

        $this->getJson(
            '/api/v1/shop-owner/analytics?date_from=2025-09-09&date_to=2026-09-11'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);

        $this->getJson(
            '/api/v1/shop-owner/analytics?date_from=2026-09-01&date_to=2026-09-11&shop_id=999'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shop_id',
            ]);
    }

    public function test_summary_status_counts_revenue_customers_and_rates_are_correct(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $customerA =
            User::factory()
                ->customer()
                ->create();

        $customerB =
            User::factory()
                ->customer()
                ->create();

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $customerA,

            date:
                '2026-09-07',

            status:
                AppointmentStatus::Completed,

            price:
                '500.00'
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $customerA,

            date:
                '2026-09-08',

            status:
                AppointmentStatus::Completed,

            price:
                '300.00'
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $customerB,

            date:
                '2026-09-09',

            status:
                AppointmentStatus::Completed,

            price:
                '200.00'
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $customerB,

            date:
                '2026-09-09',

            status:
                AppointmentStatus::NoShow,

            price:
                '999.00'
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $customerA,

            date:
                '2026-09-10',

            status:
                AppointmentStatus::Cancelled
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $customerB,

            date:
                '2026-09-10',

            status:
                AppointmentStatus::Cancelled
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $customerA,

            date:
                '2026-09-11',

            status:
                AppointmentStatus::Declined
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $customerB,

            date:
                '2026-09-11',

            status:
                AppointmentStatus::Pending
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/analytics?date_from=2026-09-07&date_to=2026-09-11'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_bookings',
                8
            )
            ->assertJsonPath(
                'data.summary.unique_customers',
                2
            )
            ->assertJsonPath(
                'data.summary.completed_revenue',
                1000
            )
            ->assertJsonPath(
                'data.status_counts.pending',
                1
            )
            ->assertJsonPath(
                'data.status_counts.confirmed',
                0
            )
            ->assertJsonPath(
                'data.status_counts.completed',
                3
            )
            ->assertJsonPath(
                'data.status_counts.no_show',
                1
            )
            ->assertJsonPath(
                'data.status_counts.cancelled',
                2
            )
            ->assertJsonPath(
                'data.status_counts.declined',
                1
            )
            ->assertJsonPath(
                'data.performance.attendance_outcomes',
                4
            )
            ->assertJsonPath(
                'data.performance.completion_rate',
                75
            )
            ->assertJsonPath(
                'data.performance.no_show_rate',
                25
            )
            ->assertJsonPath(
                'data.performance.cancellation_rate',
                25
            )
            ->assertJsonPath(
                'data.performance.decline_rate',
                12.5
            );
    }

    public function test_rating_summary_uses_only_verified_feedback_for_owned_shop_appointments_in_period(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        [, $otherShop, $otherService] =
            $this->approvedOwnerShopAndService();

        $first =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-07',

                status:
                    AppointmentStatus::Completed
            );

        $second =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-08',

                status:
                    AppointmentStatus::Completed
            );

        $outsidePeriod =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-08-01',

                status:
                    AppointmentStatus::Completed
            );

        $otherAppointment =
            $this->appointment(
                shop:
                    $otherShop,

                service:
                    $otherService,

                date:
                    '2026-09-08',

                status:
                    AppointmentStatus::Completed
            );

        $this->feedback(
            $first,
            5
        );

        $this->feedback(
            $second,
            3
        );

        $this->feedback(
            $outsidePeriod,
            1
        );

        $this->feedback(
            $otherAppointment,
            1
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/analytics?date_from=2026-09-01&date_to=2026-09-11'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.average_rating',
                4
            )
            ->assertJsonPath(
                'data.summary.review_count',
                2
            );
    }

    public function test_daily_trend_is_chronological_and_zero_fills_quiet_days(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            date:
                '2026-09-07',

            status:
                AppointmentStatus::Completed,

            price:
                '500.00'
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            date:
                '2026-09-09',

            status:
                AppointmentStatus::NoShow
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/analytics?date_from=2026-09-07&date_to=2026-09-09'
            )
            ->assertOk()
            ->assertJsonCount(
                3,
                'data.daily_trend'
            )
            ->assertJsonPath(
                'data.daily_trend.0.date',
                '2026-09-07'
            )
            ->assertJsonPath(
                'data.daily_trend.0.completed',
                1
            )
            ->assertJsonPath(
                'data.daily_trend.0.completed_revenue',
                500
            )
            ->assertJsonPath(
                'data.daily_trend.1.date',
                '2026-09-08'
            )
            ->assertJsonPath(
                'data.daily_trend.1.total_bookings',
                0
            )
            ->assertJsonPath(
                'data.daily_trend.2.date',
                '2026-09-09'
            )
            ->assertJsonPath(
                'data.daily_trend.2.no_show',
                1
            );
    }

    public function test_top_services_are_ranked_by_booking_count_with_completed_revenue_and_no_shows(): void
    {
        [$owner, $shop, $serviceA] =
            $this->approvedOwnerShopAndService(
                serviceName:
                    'Haircut',

                servicePrice:
                    '500.00'
            );

        $serviceB =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        'Hair Color',

                    'price' =>
                        '800.00',

                    'duration_minutes' =>
                        60,

                    'is_active' =>
                        true,
                ]);

        $this->appointment(
            shop:
                $shop,

            service:
                $serviceA,

            date:
                '2026-09-07',

            status:
                AppointmentStatus::Completed,

            price:
                '500.00'
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $serviceA,

            date:
                '2026-09-08',

            status:
                AppointmentStatus::Completed,

            price:
                '500.00'
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $serviceA,

            date:
                '2026-09-09',

            status:
                AppointmentStatus::NoShow,

            price:
                '500.00'
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $serviceB,

            date:
                '2026-09-07',

            status:
                AppointmentStatus::Completed,

            price:
                '800.00'
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $serviceB,

            date:
                '2026-09-08',

            status:
                AppointmentStatus::Completed,

            price:
                '800.00'
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/analytics?date_from=2026-09-01&date_to=2026-09-11'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.top_services.0.service_id',
                $serviceA->id
            )
            ->assertJsonPath(
                'data.top_services.0.service_name',
                'Haircut'
            )
            ->assertJsonPath(
                'data.top_services.0.total_bookings',
                3
            )
            ->assertJsonPath(
                'data.top_services.0.completed_bookings',
                2
            )
            ->assertJsonPath(
                'data.top_services.0.no_show_count',
                1
            )
            ->assertJsonPath(
                'data.top_services.0.completed_revenue',
                1000
            )
            ->assertJsonPath(
                'data.top_services.1.service_id',
                $serviceB->id
            )
            ->assertJsonPath(
                'data.top_services.1.total_bookings',
                2
            );
    }

    public function test_analytics_endpoint_is_read_only(): void
    {
        [$owner] =
            $this->approvedOwnerShopAndService();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->postJson(
                '/api/v1/shop-owner/analytics'
            )
            ->assertMethodNotAllowed();
    }

    /**
     * @return array{0: User, 1: Shop, 2: Service}
     */
    private function approvedOwnerShopAndService(
        string $serviceName = 'Premium Haircut',
        string $servicePrice = '500.00'
    ): array {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        $serviceName,

                    'price' =>
                        $servicePrice,

                    'duration_minutes' =>
                        60,

                    'is_active' =>
                        true,
                ]);

        return [
            $owner,
            $shop,
            $service,
        ];
    }

    private function appointment(
        Shop $shop,
        Service $service,
        string $date,
        AppointmentStatus $status,
        ?User $customer = null,
        string $price = '500.00'
    ): Appointment {
        $customer ??=
            User::factory()
                ->customer()
                ->create();

        return Appointment::factory()
            ->create([
                'customer_id' =>
                    $customer->id,

                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $service->id,

                'appointment_date' =>
                    $date,

                'starts_at' =>
                    '10:00:00',

                'ends_at' =>
                    '11:00:00',

                'service_name' =>
                    $service->name,

                'price_snapshot' =>
                    $price,

                'duration_minutes_snapshot' =>
                    60,

                'status' =>
                    $status,

                'completed_at' =>
                    $status
                    === AppointmentStatus::Completed
                        ? now()
                        : null,
            ]);
    }

    private function feedback(
        Appointment $appointment,
        int $rating
    ): Feedback {
        return Feedback::factory()
            ->create([
                'appointment_id' =>
                    $appointment->id,

                'customer_id' =>
                    $appointment->customer_id,

                'shop_id' =>
                    $appointment->shop_id,

                'rating' =>
                    $rating,
            ]);
    }
}