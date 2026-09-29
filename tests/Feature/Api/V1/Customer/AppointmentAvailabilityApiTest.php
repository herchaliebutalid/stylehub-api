<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\ShopAvailabilityStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentAvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixed application time used by every test.
     *
     * September 7, 2026 is a Monday.
     */
    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Test Configuration
        |--------------------------------------------------------------------------
        */

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

            /*
             * Never send real SMS while running automated tests.
             */
            'sms.enabled' =>
                false,

            'sms.live_test.enabled' =>
                false,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Freeze Time
        |--------------------------------------------------------------------------
        |
        | Using a fixed time makes appointment tests deterministic.
        |
        | Current test time:
        |
        | Monday
        | September 7, 2026
        | 8:00 AM
        | Asia/Manila
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

    /*
    |--------------------------------------------------------------------------
    | Authentication / Authorization
    |--------------------------------------------------------------------------
    */

    public function test_available_dates_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/customer/shops/1/services/1/available-dates'
        )
            ->assertUnauthorized()
            ->assertJsonPath(
                'success',
                false
            );
    }

    public function test_available_slots_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/customer/shops/1/services/1/available-slots?date=2026-09-07'
        )
            ->assertUnauthorized()
            ->assertJsonPath(
                'success',
                false
            );
    }

    public function test_shop_owner_cannot_access_customer_availability_routes(): void
    {
        $shopOwner =
            User::factory()
                ->shopOwner()
                ->create();

        $this->actingAs(
            $shopOwner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops/1/services/1/available-dates'
            )
            ->assertForbidden()
            ->assertJsonPath(
                'success',
                false
            );
    }

    public function test_suspended_customer_cannot_access_availability_routes(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->suspended()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops/1/services/1/available-dates'
            )
            ->assertForbidden()
            ->assertJsonPath(
                'success',
                false
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Available Dates
    |--------------------------------------------------------------------------
    */

    public function test_customer_receives_real_available_dates(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        /*
         * Monday is open from 09:00 to 12:00.
         *
         * Tuesday is explicitly closed.
         */
        ShopSchedule::factory()
            ->forDay(
                DayOfWeek::Tuesday
            )
            ->closed()
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )
                ->getJson(
                    "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-dates?days=2"
                );

        $response
            ->assertOk()

            ->assertJsonPath(
                'success',
                true
            )

            ->assertJsonPath(
                'data.timezone',
                'Asia/Manila'
            )

            ->assertJsonPath(
                'data.slot_interval_minutes',
                15
            )

            /*
             * Monday
             */
            ->assertJsonPath(
                'data.dates.0.date',
                '2026-09-07'
            )

            ->assertJsonPath(
                'data.dates.0.day_label',
                'Monday'
            )

            ->assertJsonPath(
                'data.dates.0.is_open',
                true
            )

            ->assertJsonPath(
                'data.dates.0.is_available',
                true
            )

            /*
             * 60-minute service
             * Shop: 09:00 - 12:00
             * Interval: 15 minutes
             *
             * Valid starts:
             *
             * 09:00
             * 09:15
             * 09:30
             * 09:45
             * 10:00
             * 10:15
             * 10:30
             * 10:45
             * 11:00
             *
             * = 9 slots
             */
            ->assertJsonPath(
                'data.dates.0.available_slot_count',
                9
            )

            /*
             * Tuesday
             */
            ->assertJsonPath(
                'data.dates.1.date',
                '2026-09-08'
            )

            ->assertJsonPath(
                'data.dates.1.is_open',
                false
            )

            ->assertJsonPath(
                'data.dates.1.is_available',
                false
            )

            ->assertJsonPath(
                'data.dates.1.unavailable_reason',
                'shop_closed'
            );
    }

    public function test_available_dates_validate_days_parameter(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-dates?days=61"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'days',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Available Time Slots
    |--------------------------------------------------------------------------
    */

    public function test_customer_receives_correct_slot_grid(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )
                ->getJson(
                    "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
                );

        $response
            ->assertOk()

            ->assertJsonPath(
                'success',
                true
            )

            ->assertJsonPath(
                'data.date.value',
                '2026-09-07'
            )

            ->assertJsonPath(
                'data.date.is_open',
                true
            )

            ->assertJsonPath(
                'data.date.opens_at',
                '09:00'
            )

            ->assertJsonPath(
                'data.date.closes_at',
                '12:00'
            )

            ->assertJsonPath(
                'data.slots.0.starts_at',
                '09:00'
            )

            ->assertJsonPath(
                'data.slots.0.ends_at',
                '10:00'
            )

            ->assertJsonPath(
                'data.slots.0.label',
                '9:00 AM - 10:00 AM'
            )

            ->assertJsonPath(
                'data.slots.8.starts_at',
                '11:00'
            )

            ->assertJsonPath(
                'data.slots.8.ends_at',
                '12:00'
            );

        $this->assertCount(
            9,
            $response->json(
                'data.slots'
            )
        );
    }

    public function test_available_slots_remove_times_conflicting_with_shop_booking(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        /*
         * Existing booking:
         *
         * 09:30 - 10:30
         */
        $this->createAppointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $otherCustomer,

            startsAt:
                '09:30:00',

            endsAt:
                '10:30:00',

            status:
                AppointmentStatus::Confirmed,
        );

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )
                ->getJson(
                    "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
                );

        $response
            ->assertOk()

            /*
             * 09:00 - 10:00 overlaps.
             */
            ->assertJsonMissing([
                'starts_at' =>
                    '09:00',
            ])

            /*
             * 09:30 - 10:30 overlaps exactly.
             */
            ->assertJsonMissing([
                'starts_at' =>
                    '09:30',
            ])

            /*
             * 10:30 starts exactly when the existing
             * booking finishes, so it is allowed.
             */
            ->assertJsonFragment([
                'starts_at' =>
                    '10:30',

                'ends_at' =>
                    '11:30',
            ]);
    }

    public function test_customer_cannot_receive_slots_that_conflict_with_their_other_shop_booking(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        [
            $otherShop,
            $otherService,
        ] =
            $this->createBookableShopAndService();

        /*
         * Same customer already has another appointment:
         *
         * 10:00 - 11:00
         *
         * at a completely different shop.
         */
        $this->createAppointment(
            shop:
                $otherShop,

            service:
                $otherService,

            customer:
                $customer,

            startsAt:
                '10:00:00',

            endsAt:
                '11:00:00',

            status:
                AppointmentStatus::Pending,
        );

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )
                ->getJson(
                    "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
                );

        $response
            ->assertOk()

            /*
             * 09:00 - 10:00 is allowed because one appointment
             * ends exactly when the other begins.
             */
            ->assertJsonFragment([
                'starts_at' =>
                    '09:00',

                'ends_at' =>
                    '10:00',
            ])

            /*
             * 09:15 - 10:15 conflicts.
             */
            ->assertJsonMissing([
                'starts_at' =>
                    '09:15',
            ])

            /*
             * 10:00 - 11:00 conflicts exactly.
             */
            ->assertJsonMissing([
                'starts_at' =>
                    '10:00',
            ])

            /*
             * 11:00 - 12:00 is allowed because the previous
             * appointment ends exactly at 11:00.
             */
            ->assertJsonFragment([
                'starts_at' =>
                    '11:00',

                'ends_at' =>
                    '12:00',
            ]);
    }

    public function test_cancelled_appointment_does_not_block_time_slot(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        $this->createAppointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $otherCustomer,

            startsAt:
                '09:00:00',

            endsAt:
                '10:00:00',

            status:
                AppointmentStatus::Cancelled,
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
            )
            ->assertOk()
            ->assertJsonFragment([
                'starts_at' =>
                    '09:00',

                'ends_at' =>
                    '10:00',
            ]);
    }

    public function test_completed_appointment_does_not_block_time_slot(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        $this->createAppointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $otherCustomer,

            startsAt:
                '09:00:00',

            endsAt:
                '10:00:00',

            status:
                AppointmentStatus::Completed,
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
            )
            ->assertOk()
            ->assertJsonFragment([
                'starts_at' =>
                    '09:00',

                'ends_at' =>
                    '10:00',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tenant / Resource Security
    |--------------------------------------------------------------------------
    */

    public function test_service_must_belong_to_selected_shop(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
        ] =
            $this->createBookableShopAndService();

        [
            ,
            $otherService,
        ] =
            $this->createBookableShopAndService();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$otherService->id}/available-dates"
            )
            ->assertNotFound();
    }

    public function test_inactive_service_is_not_exposed_through_availability_api(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        $service->is_active =
            false;

        $service->save();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-dates"
            )
            ->assertNotFound();
    }

    public function test_suspended_shop_owner_shop_is_not_exposed(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $suspendedOwner =
            User::factory()
                ->shopOwner()
                ->suspended()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'owner_id' =>
                        $suspendedOwner->id,

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

                    'duration_minutes' =>
                        60,

                    'is_active' =>
                        true,
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-dates"
            )
            ->assertNotFound();
    }

    public function test_shop_not_accepting_appointments_returns_no_slots(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        $shop->availability_status =
            ShopAvailabilityStatus::Closed;

        $shop->save();

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )
                ->getJson(
                    "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
                );

        $response
            ->assertOk()

            ->assertJsonPath(
                'data.date.is_open',
                false
            )

            ->assertJsonPath(
                'data.date.unavailable_reason',
                'shop_not_accepting_appointments'
            )

            ->assertJsonCount(
                0,
                'data.slots'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Date Security
    |--------------------------------------------------------------------------
    */

    public function test_slots_reject_past_date(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-06"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
            ]);
    }

    public function test_slots_reject_date_outside_booking_window(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        /*
         * Current date:
         * September 7, 2026
         *
         * Booking window:
         * 60 days
         *
         * January 1, 2027 is well outside the window.
         */
        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2027-01-01"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
            ]);
    }

    public function test_slots_reject_invalid_date_format(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=09-07-2026"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Endpoint Security
    |--------------------------------------------------------------------------
    */

    public function test_booking_rejects_forged_time_outside_server_slot_grid(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        /*
         * Server generates:
         *
         * 09:00
         * 09:15
         * 09:30
         * ...
         *
         * 09:07 must never be accepted even if a malicious
         * client manually sends it.
         */
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
                        '09:07',

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

    public function test_valid_server_generated_slot_can_be_booked(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        /*
         * First verify that 09:15 is really returned
         * by the availability API.
         */
        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
            )
            ->assertOk()
            ->assertJsonFragment([
                'starts_at' =>
                    '09:15',

                'ends_at' =>
                    '10:15',
            ]);

        /*
         * Now book that exact server-generated slot.
         */
        $response =
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
                            '09:15',

                        'notes' =>
                            'Automated booking test.',
                    ]
                );

        $response
            ->assertCreated()

            ->assertJsonPath(
                'success',
                true
            )

            ->assertJsonPath(
                'data.appointment.appointment_date',
                '2026-09-07'
            )

            ->assertJsonPath(
                'data.appointment.starts_at',
                '09:15:00'
            )

            /*
             * The client never supplied ends_at.
             *
             * Laravel calculated this using the
             * 60-minute service duration.
             */
            ->assertJsonPath(
                'data.appointment.ends_at',
                '10:15:00'
            )

            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Pending
                    ->value
            )

            ->assertJsonPath(
                'data.appointment.service_snapshot.name',
                'Premium Haircut'
            )

            ->assertJsonPath(
                'data.appointment.service_snapshot.price',
                '500.00'
            )

            ->assertJsonPath(
                'data.appointment.service_snapshot.duration_minutes',
                60
            );

        $this->assertDatabaseHas(
            'appointments',
            [
                'customer_id' =>
                    $customer->id,

                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $service->id,

                'appointment_date' =>
                    '2026-09-07',

                'starts_at' =>
                    '09:15:00',

                'ends_at' =>
                    '10:15:00',

                'service_name' =>
                    'Premium Haircut',

                'price_snapshot' =>
                    '500.00',

                'duration_minutes_snapshot' =>
                    60,

                'status' =>
                    AppointmentStatus::Pending
                        ->value,
            ]
        );
    }

    public function test_booking_rechecks_slot_after_availability_was_loaded(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [
            $shop,
            $service,
        ] =
            $this->createBookableShopAndService();

        /*
         * STEP 1:
         *
         * Customer loads availability.
         *
         * 09:00 is currently free.
         */
        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
            )
            ->assertOk()
            ->assertJsonFragment([
                'starts_at' =>
                    '09:00',

                'ends_at' =>
                    '10:00',
            ]);

        /*
         * STEP 2:
         *
         * Another customer books that time before
         * our first customer presses Submit.
         */
        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        $this->createAppointment(
            shop:
                $shop,

            service:
                $service,

            customer:
                $otherCustomer,

            startsAt:
                '09:00:00',

            endsAt:
                '10:00:00',

            status:
                AppointmentStatus::Pending,
        );

        /*
         * STEP 3:
         *
         * First customer's screen is now stale.
         *
         * The booking endpoint MUST independently
         * validate availability again.
         */
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
                'starts_at',
            ]);

        /*
         * Only the other customer's appointment
         * should exist.
         */
        $this->assertDatabaseCount(
            'appointments',
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Test Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create one realistic bookable shop.
     *
     * Monday:
     * 09:00 - 12:00
     *
     * Service:
     * Premium Haircut
     * 60 minutes
     * ₱500
     *
     * @return array{0: Shop, 1: Service}
     */
    private function createBookableShopAndService(): array
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create([
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

                    'description' =>
                        'Automated test service.',

                    'price' =>
                        '500.00',

                    'duration_minutes' =>
                        60,

                    'is_active' =>
                        true,
                ]);

        return [
            $shop,
            $service,
        ];
    }

    /**
     * Create an appointment directly for availability tests.
     *
     * Direct database setup is intentional here because these tests
     * need to prepare existing booking states before testing the API.
     */
    private function createAppointment(
        Shop $shop,
        Service $service,
        User $customer,
        string $startsAt,
        string $endsAt,
        AppointmentStatus $status
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
                    $status,
            ]);
    }
}