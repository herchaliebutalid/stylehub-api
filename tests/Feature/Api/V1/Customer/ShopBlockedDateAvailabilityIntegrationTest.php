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
use App\Models\ShopBlockedDate;
use App\Models\ShopSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopBlockedDateAvailabilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' =>
                'Asia/Manila',

            'appointments.slot_interval_minutes' =>
                15,

            'appointments.minimum_lead_time_minutes' =>
                0,

            'appointments.booking_window_days' =>
                60,

            'appointments.availability_preview_days' =>
                30,

            'sms.enabled' =>
                false,

            'sms.live_test.enabled' =>
                false,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Fixed Test Time
        |--------------------------------------------------------------------------
        |
        | September 7, 2026 is Monday.
        |
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

    public function test_blocked_date_is_unavailable_in_customer_date_picker(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] =
            $this->environment();

        ShopBlockedDate::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'blocked_date' =>
                    '2026-09-07',

                'reason' =>
                    'Holiday closure',
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
                false
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
                'data.dates.0.opens_at',
                null
            )
            ->assertJsonPath(
                'data.dates.0.closes_at',
                null
            )
            ->assertJsonPath(
                'data.dates.0.unavailable_reason',
                'shop_blocked_date'
            )

            /*
             * Owner's private closure note must not leak to customers.
             */
            ->assertJsonMissingPath(
                'data.dates.0.blocked_reason'
            );
    }

    public function test_blocked_date_returns_zero_customer_slots(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] =
            $this->environment();

        ShopBlockedDate::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'blocked_date' =>
                    '2026-09-07',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.date.value',
                '2026-09-07'
            )
            ->assertJsonPath(
                'data.date.is_open',
                false
            )
            ->assertJsonPath(
                'data.date.opens_at',
                null
            )
            ->assertJsonPath(
                'data.date.closes_at',
                null
            )
            ->assertJsonPath(
                'data.date.unavailable_reason',
                'shop_blocked_date'
            )
            ->assertJsonCount(
                0,
                'data.slots'
            );
    }

    public function test_forged_direct_booking_on_blocked_date_is_rejected(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] =
            $this->environment();

        ShopBlockedDate::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'blocked_date' =>
                    '2026-09-07',
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
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'appointment_date',
            ]);

        $this->assertDatabaseCount(
            'appointments',
            0
        );
    }

    public function test_another_shops_blocked_date_does_not_affect_this_shop(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] =
            $this->environment();

        $otherShop =
            Shop::factory()
                ->approved()
                ->create([
                    'availability_status' =>
                        ShopAvailabilityStatus::Open,
                ]);

        ShopBlockedDate::factory()
            ->create([
                'shop_id' =>
                    $otherShop->id,

                'blocked_date' =>
                    '2026-09-07',
            ]);

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
            ->assertJsonPath(
                'data.date.unavailable_reason',
                null
            )
            ->assertJsonCount(
                9,
                'data.slots'
            );
    }

    public function test_customer_cannot_request_reschedule_to_blocked_date(): void
    {
        [
            ,
            $customer,
            $shop,
            $service,
        ] =
            $this->environment();

        /*
         * Appointment exists BEFORE the shop blocks this date.
         */
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

        ShopBlockedDate::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'blocked_date' =>
                    '2026-09-07',
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
                        '11:00',

                    'reason' =>
                        'Move it later.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_date',
            ]);

        $this->assertDatabaseCount(
            'reschedule_requests',
            0
        );
    }

    public function test_owner_cannot_approve_request_after_requested_date_becomes_blocked(): void
    {
        [
            $owner,
            $customer,
            $shop,
            $service,
        ] =
            $this->environment();

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

        /*
         * Customer requested this slot while the date was still open.
         */
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

        /*
         * The owner blocks the date BEFORE approving the request.
         */
        ShopBlockedDate::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'blocked_date' =>
                    '2026-09-07',
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
                'appointment_date',
            ]);

        /*
         * Failed approval must roll back both objects.
         */
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

    public function test_existing_appointment_can_still_be_confirmed_after_date_is_blocked(): void
    {
        [
            $owner,
            $customer,
            $shop,
            $service,
        ] =
            $this->environment();

        /*
         * Existing booking created before the closure.
         */
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

        ShopBlockedDate::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'blocked_date' =>
                    '2026-09-07',
            ]);

        /*
         * Blocking a day prevents NEW/moved bookings.
         *
         * It must not prevent the owner from handling an appointment that
         * existed before the closure was created.
         */
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
                AppointmentStatus::Confirmed
                    ->value
            );

        $this->assertSame(
            AppointmentStatus::Confirmed,
            $appointment
                ->fresh()
                ->status
        );
    }

    /**
     * Create a verified customer, approved Shop Owner, open Monday shop,
     * and one active 60-minute service.
     *
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

    /**
     * Create an appointment that existed before a date was blocked.
     */
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
                    $service
                        ->duration_minutes,

                'status' =>
                    AppointmentStatus::Pending,
            ]);
    }
}