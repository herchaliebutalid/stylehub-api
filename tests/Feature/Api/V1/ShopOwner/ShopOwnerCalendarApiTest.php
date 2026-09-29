<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopBlockedDate;
use App\Models\ShopBreakPeriod;
use App\Models\ShopSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOwnerCalendarApiTest extends TestCase
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
                year: 2026,
                month: 9,
                day: 11,
                hour: 8,
                minute: 0,
                second: 0,
                timezone: 'Asia/Manila'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_calendar_routes_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/calendar?month=2026-09'
        )->assertUnauthorized();

        $this->getJson(
            '/api/v1/shop-owner/calendar/agenda?date=2026-09-11'
        )->assertUnauthorized();
    }

    public function test_customer_and_admin_cannot_use_shop_owner_calendar(): void
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
            )->getJson(
                '/api/v1/shop-owner/calendar?month=2026-09'
            )->assertForbidden();
        }
    }

    public function test_pending_shop_owner_cannot_use_calendar(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()->create([
            'owner_id' =>
                $owner->id,
        ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/calendar?month=2026-09'
        )->assertForbidden();
    }

    public function test_month_endpoint_returns_complete_month_with_shop_context(): void
    {
        [
            $owner,
            $shop,
            $service,
        ] =
            $this->approvedOwnerShopAndService();

        ShopSchedule::factory()
            ->forDay(
                DayOfWeek::Friday
            )
            ->openBetween(
                '09:00:00',
                '18:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Friday
            )
            ->between(
                '12:00:00',
                '13:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,

                'label' =>
                    'Lunch',
            ]);

        ShopBlockedDate::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'blocked_date' =>
                    '2026-09-18',

                'reason' =>
                    'Private event',
            ]);

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            date:
                '2026-09-11',

            startsAt:
                '09:00:00',

            endsAt:
                '09:30:00',

            status:
                AppointmentStatus::Pending
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            date:
                '2026-09-11',

            startsAt:
                '10:00:00',

            endsAt:
                '10:30:00',

            status:
                AppointmentStatus::Confirmed
        );

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            date:
                '2026-09-11',

            startsAt:
                '11:00:00',

            endsAt:
                '11:30:00',

            status:
                AppointmentStatus::Cancelled
        );

        $response =
            $this->actingAs(
                $owner,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/shop-owner/calendar?month=2026-09'
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.month',
                    '2026-09'
                )
                ->assertJsonPath(
                    'data.month_label',
                    'September 2026'
                )
                ->assertJsonCount(
                    30,
                    'data.days'
                );

        $day =
            collect(
                $response->json(
                    'data.days'
                )
            )->firstWhere(
                'date',
                '2026-09-11'
            );

        $this->assertNotNull(
            $day
        );

        $this->assertTrue(
            $day[
                'is_today'
            ]
        );

        $this->assertTrue(
            $day[
                'schedule'
            ][
                'is_open'
            ]
        );

        $this->assertSame(
            '09:00',
            $day[
                'schedule'
            ][
                'opens_at'
            ]
        );

        $this->assertSame(
            '18:00',
            $day[
                'schedule'
            ][
                'closes_at'
            ]
        );

        $this->assertSame(
            1,
            $day[
                'break_period_count'
            ]
        );

        $this->assertSame(
            3,
            $day[
                'appointment_count'
            ]
        );

        $this->assertSame(
            2,
            $day[
                'active_appointment_count'
            ]
        );

        $this->assertSame(
            1,
            $day[
                'status_counts'
            ][
                'pending'
            ]
        );

        $this->assertSame(
            1,
            $day[
                'status_counts'
            ][
                'confirmed'
            ]
        );

        $this->assertSame(
            1,
            $day[
                'status_counts'
            ][
                'cancelled'
            ]
        );

        $blockedDay =
            collect(
                $response->json(
                    'data.days'
                )
            )->firstWhere(
                'date',
                '2026-09-18'
            );

        $this->assertNotNull(
            $blockedDay
        );

        $this->assertTrue(
            $blockedDay[
                'is_blocked'
            ]
        );

        $this->assertSame(
            'Private event',
            $blockedDay[
                'blocked_reason'
            ]
        );
    }

    public function test_month_endpoint_never_counts_another_shops_appointments(): void
    {
        [
            $owner,
            $shop,
            $service,
        ] =
            $this->approvedOwnerShopAndService();

        $otherShop =
            Shop::factory()
                ->approved()
                ->create();

        $otherService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $otherShop->id,
                ]);

        $this->appointment(
            shop:
                $shop,

            service:
                $service,

            date:
                '2026-09-11'
        );

        $this->appointment(
            shop:
                $otherShop,

            service:
                $otherService,

            date:
                '2026-09-11'
        );

        $response =
            $this->actingAs(
                $owner,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/shop-owner/calendar?month=2026-09'
                )
                ->assertOk();

        $day =
            collect(
                $response->json(
                    'data.days'
                )
            )->firstWhere(
                'date',
                '2026-09-11'
            );

        $this->assertSame(
            1,
            $day[
                'appointment_count'
            ]
        );
    }

    public function test_month_defaults_to_current_month(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerShopAndService();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/calendar'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.month',
                '2026-09'
            );
    }

    public function test_invalid_or_unknown_month_query_is_rejected(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerShopAndService();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/calendar?month=09-2026'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'month',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/calendar?shop_id=999'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shop_id',
            ]);
    }

    public function test_day_agenda_returns_schedule_breaks_blocked_state_and_chronological_appointments(): void
    {
        [
            $owner,
            $shop,
            $service,
        ] =
            $this->approvedOwnerShopAndService();

        ShopSchedule::factory()
            ->forDay(
                DayOfWeek::Friday
            )
            ->openBetween(
                '09:00:00',
                '18:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Friday
            )
            ->between(
                '12:00:00',
                '13:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,

                'label' =>
                    'Lunch break',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Existing Appointments First
        |--------------------------------------------------------------------------
        |
        | These bookings already existed before the Shop Owner later blocked
        | this date. Blocked Dates protect new bookings but intentionally do
        | not remove existing appointments from the owner's agenda.
        |
        */

        $later =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-11',

                startsAt:
                    '14:00:00',

                endsAt:
                    '14:30:00',

                status:
                    AppointmentStatus::Confirmed
            );

        $earlier =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-11',

                startsAt:
                    '10:00:00',

                endsAt:
                    '10:30:00',

                status:
                    AppointmentStatus::Pending
            );

        /*
        |--------------------------------------------------------------------------
        | Owner Blocks Date Afterwards
        |--------------------------------------------------------------------------
        |
        | This is a valid real-world state: existing appointments remain
        | manageable while new appointments/reschedules are prevented.
        |
        */

        ShopBlockedDate::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'blocked_date' =>
                    '2026-09-11',

                'reason' =>
                    'Owner unavailable',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/calendar/agenda?date=2026-09-11'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.date.value',
                '2026-09-11'
            )
            ->assertJsonPath(
                'data.date.day_label',
                'Friday'
            )
            ->assertJsonPath(
                'data.schedule.opens_at',
                '09:00'
            )
            ->assertJsonPath(
                'data.blocked_date.reason',
                'Owner unavailable'
            )
            ->assertJsonPath(
                'data.break_periods.0.starts_at',
                '12:00'
            )
            ->assertJsonPath(
                'data.break_periods.0.ends_at',
                '13:00'
            )
            ->assertJsonPath(
                'data.break_periods.0.label',
                'Lunch break'
            )
            ->assertJsonPath(
                'data.summary.appointment_count',
                2
            )
            ->assertJsonPath(
                'data.appointments.0.id',
                $earlier->id
            )
            ->assertJsonPath(
                'data.appointments.1.id',
                $later->id
            )
            ->assertJsonMissingPath(
                'data.break_periods.0.shop_id'
            );
    }

    public function test_day_agenda_returns_empty_appointments_for_a_quiet_day(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerShopAndService();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/calendar/agenda?date=2026-09-12'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.appointment_count',
                0
            )
            ->assertJsonCount(
                0,
                'data.appointments'
            );
    }

    public function test_day_agenda_requires_valid_date_and_rejects_unknown_query_fields(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerShopAndService();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/calendar/agenda'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/calendar/agenda?date=11-09-2026'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/calendar/agenda?date=2026-09-11&customer_id=1'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_id',
            ]);
    }

    /**
     * @return array{0: User, 1: Shop, 2: Service}
     */
    private function approvedOwnerShopAndService(): array
    {
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
        string $startsAt = '10:00:00',
        string $endsAt = '10:30:00',
        AppointmentStatus $status = AppointmentStatus::Pending
    ): Appointment {
        return Appointment::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $service->id,

                'appointment_date' =>
                    $date,

                'starts_at' =>
                    $startsAt,

                'ends_at' =>
                    $endsAt,

                'service_name' =>
                    $service->name,

                'price_snapshot' =>
                    $service->price,

                'duration_minutes_snapshot' =>
                    $service
                        ->duration_minutes,

                'status' =>
                    $status,
            ]);
    }
}