<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\RescheduleRequestStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopBreakPeriod;
use App\Models\ShopSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopBreakPeriodAvailabilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Manila',
            'appointments.slot_interval_minutes' => 15,
            'appointments.minimum_lead_time_minutes' => 0,
            'appointments.booking_window_days' => 60,
            'appointments.availability_preview_days' => 30,
            'sms.enabled' => false,
            'sms.live_test.enabled' => false,
        ]);

        /*
         * September 7, 2026 is Monday.
         */
        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                year: 2026,
                month: 9,
                day: 7,
                hour: 8,
                minute: 0,
                second: 0,
                timezone: 'Asia/Manila',
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_break_period_removes_all_overlapping_customer_slots(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] = $this->environment();

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->between(
                '10:00:00',
                '11:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,

                'label' =>
                    'Private staff break',
            ]);

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )
                ->getJson(
                    "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.date.is_open',
                    true
                )
                ->assertJsonMissingPath(
                    'data.date.break_periods'
                );

        $starts =
            collect(
                $response->json(
                    'data.slots'
                )
            )
                ->pluck(
                    'starts_at'
                )
                ->all();

        $this->assertSame(
            [
                '09:00',
                '11:00',
            ],
            $starts
        );
    }

    public function test_break_period_reduces_available_date_slot_count(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] = $this->environment();

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->between(
                '10:00:00',
                '11:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-dates?days=1"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.dates.0.date',
                '2026-09-07'
            )
            ->assertJsonPath(
                'data.dates.0.is_open',
                true
            )
            ->assertJsonPath(
                'data.dates.0.is_available',
                true
            )
            ->assertJsonPath(
                'data.dates.0.available_slot_count',
                2
            );
    }

    public function test_break_covering_operating_hours_makes_date_unavailable(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] = $this->environment();

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->between(
                '09:00:00',
                '12:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-dates?days=1"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.dates.0.is_open',
                true
            )
            ->assertJsonPath(
                'data.dates.0.is_available',
                false
            )
            ->assertJsonPath(
                'data.dates.0.available_slot_count',
                0
            )
            ->assertJsonPath(
                'data.dates.0.unavailable_reason',
                'no_available_slots'
            );
    }

    public function test_forged_direct_booking_overlapping_break_is_rejected(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] = $this->environment();

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->between(
                '10:00:00',
                '11:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                '/api/v1/customer/appointments',
                [
                    'shop_id' =>
                        $shop->id,

                    'service_id' =>
                        $service->id,

                    'appointment_date' =>
                        '2026-09-07',

                    'starts_at' =>
                        '09:30',

                    'notes' =>
                        null,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'starts_at',
            ]);

        $this->assertDatabaseCount(
            'appointments',
            0
        );
    }

    public function test_appointment_ending_exactly_when_break_starts_is_allowed(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] = $this->environment();

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->between(
                '10:00:00',
                '11:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                '/api/v1/customer/appointments',
                [
                    'shop_id' =>
                        $shop->id,

                    'service_id' =>
                        $service->id,

                    'appointment_date' =>
                        '2026-09-07',

                    'starts_at' =>
                        '09:00',

                    'notes' =>
                        null,
                ]
            )
            ->assertCreated();
    }

    public function test_break_period_on_another_weekday_or_shop_does_not_affect_slots(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] = $this->environment();

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Tuesday
            )
            ->between(
                '09:00:00',
                '12:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $otherShop =
            Shop::factory()
                ->approved()
                ->create();

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->between(
                '09:00:00',
                '12:00:00'
            )
            ->create([
                'shop_id' =>
                    $otherShop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
            )
            ->assertOk()
            ->assertJsonCount(
                9,
                'data.slots'
            );
    }

    public function test_customer_cannot_request_reschedule_overlapping_break(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] = $this->environment();

        $appointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                startsAt:
                    '09:00:00',

                endsAt:
                    '10:00:00',
            );

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->between(
                '10:00:00',
                '11:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
                [
                    'requested_date' =>
                        '2026-09-07',

                    'requested_starts_at' =>
                        '10:00',

                    'reason' =>
                        'Move it later.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_starts_at',
            ]);

        $this->assertDatabaseCount(
            'reschedule_requests',
            0
        );
    }

    public function test_owner_cannot_approve_request_after_requested_time_becomes_break(): void
    {
        [
            $owner,
            $customer,
            $shop,
            $service,
        ] = $this->environment();

        $appointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                startsAt:
                    '09:00:00',

                endsAt:
                    '10:00:00',
            );

        $request =
            RescheduleRequest::factory()
                ->create([
                    'appointment_id' =>
                        $appointment->id,

                    'requested_date' =>
                        '2026-09-07',

                    'requested_starts_at' =>
                        '11:00:00',

                    'requested_ends_at' =>
                        '12:00:00',

                    'status' =>
                        RescheduleRequestStatus::Pending,
                ]);

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->between(
                '11:00:00',
                '12:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'starts_at',
            ]);

        $this->assertSame(
            '09:00:00',
            $appointment
                ->fresh()
                ->starts_at
        );

        $this->assertSame(
            RescheduleRequestStatus::Pending,
            $request
                ->fresh()
                ->status
        );
    }

    public function test_existing_appointment_can_still_be_confirmed_after_break_is_added(): void
    {
        [
            $owner,
            $customer,
            $shop,
            $service,
        ] = $this->environment();

        $appointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                startsAt:
                    '10:00:00',

                endsAt:
                    '11:00:00',
            );

        ShopBreakPeriod::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->between(
                '10:00:00',
                '11:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/confirm"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Confirmed->value
            );
    }

    /**
     * @return array{0: User, 1: User, 2: Shop, 3: Service}
     */
    private function environment(): array
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'owner_id' =>
                        $owner->id,

                    'availability_status' =>
                        ShopAvailabilityStatus::Open,
                ]);

        ShopSchedule::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->openBetween(
                '09:00:00',
                '12:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        'Premium Haircut',

                    'price' =>
                        '500.00',

                    'duration_minutes' =>
                        60,

                    'is_active' =>
                        true,
                ]);

        return [
            $owner,
            $customer,
            $shop,
            $service,
        ];
    }

    private function appointment(
        User $customer,
        Shop $shop,
        Service $service,
        string $startsAt,
        string $endsAt
    ): Appointment {
        return Appointment::factory()
            ->create([
                'customer_id' =>
                    $customer->id,

                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $service->id,

                'appointment_date' =>
                    '2026-09-07',

                'starts_at' =>
                    $startsAt,

                'ends_at' =>
                    $endsAt,

                'service_name' =>
                    $service->name,

                'price_snapshot' =>
                    $service->price,

                'duration_minutes_snapshot' =>
                    $service->duration_minutes,

                'status' =>
                    AppointmentStatus::Pending,
            ]);
    }
}