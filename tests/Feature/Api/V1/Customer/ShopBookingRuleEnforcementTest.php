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
use App\Models\ShopBookingRule;
use App\Models\ShopSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopBookingRuleEnforcementTest extends TestCase
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

            'appointments.cancellation_cutoff_minutes' =>
                0,

            'appointments.reschedule_cutoff_minutes' =>
                0,

            'sms.enabled' =>
                false,

            'sms.live_test.enabled' =>
                false,
        ]);

        /*
         * Monday, September 7, 2026 at 8:00 AM.
         */
        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                year:
                    2026,

                month:
                    9,

                day:
                    7,

                hour:
                    8,

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

    public function test_custom_slot_interval_changes_customer_availability_grid(): void
    {
        [$shop, $service] =
            $this->bookableShopAndService();

        $this->saveRules(
            shop:
                $shop,

            slotInterval:
                30
        );

        $customer =
            User::factory()
                ->customer()
                ->create();

        /*
         * Shop hours: 09:00-18:00
         * Service:    60 minutes
         * Interval:   30 minutes
         *
         * Valid starts:
         *
         * 09:00, 09:30, 10:00, 10:30,
         * 11:00, 11:30, 12:00, 12:30,
         * 13:00, 13:30, 14:00, 14:30,
         * 15:00, 15:30, 16:00, 16:30,
         * 17:00
         *
         * 17:00 is valid because the service ends
         * exactly when the shop closes at 18:00.
         */

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.slot_interval_minutes',
                30
            )
            ->assertJsonCount(
                17,
                'data.slots'
            )
            ->assertJsonPath(
                'data.slots.0.starts_at',
                '09:00'
            )
            ->assertJsonPath(
                'data.slots.1.starts_at',
                '09:30'
            )
            ->assertJsonPath(
                'data.slots.16.starts_at',
                '17:00'
            );
    }

    public function test_custom_minimum_lead_time_removes_too_soon_slots(): void
    {
        [$shop, $service] =
            $this->bookableShopAndService();

        $this->saveRules(
            shop:
                $shop,

            slotInterval:
                30,

            leadMinutes:
                120
        );

        $customer =
            User::factory()
                ->customer()
                ->create();

        /*
         * Now = 08:00
         * Lead = 120 minutes
         *
         * First legal slot = 10:00
         */
        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-07"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.slots.0.starts_at',
                '10:00'
            );
    }

    public function test_custom_booking_window_rejects_dates_outside_shop_window(): void
    {
        [$shop, $service] =
            $this->bookableShopAndService();

        $this->saveRules(
            shop:
                $shop,

            bookingWindowDays:
                2
        );

        $customer =
            User::factory()
                ->customer()
                ->create();

        /*
         * September 10 is three days after September 7.
         */
        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-10"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
            ]);
    }

    public function test_direct_booking_cannot_bypass_custom_slot_interval(): void
    {
        [$shop, $service] =
            $this->bookableShopAndService();

        $this->saveRules(
            shop:
                $shop,

            slotInterval:
                30
        );

        $customer =
            User::factory()
                ->customer()
                ->create();

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

                    /*
                     * 09:15 is valid under the old global
                     * 15-minute interval but invalid under
                     * this shop's 30-minute interval.
                     */
                    'starts_at' =>
                        '09:15',
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

    public function test_customer_cancellation_cutoff_is_enforced_and_exact_boundary_is_allowed(): void
    {
        [$shop, $service] =
            $this->bookableShopAndService();

        $customer =
            User::factory()
                ->customer()
                ->create();

        /*
         * Current time = 08:00.
         * Appointment = 10:00.
         *
         * 121-minute cutoff means deadline was 07:59.
         */
        $tooLateAppointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                startsAt:
                    '10:00:00'
            );

        $this->saveRules(
            shop:
                $shop,

            cancellationCutoffMinutes:
                121
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/customer/appointments/{$tooLateAppointment->id}/cancel",
                [
                    'reason' =>
                        'My plans changed unexpectedly.',
                ]
            )
            ->assertConflict();

        $this->assertSame(
            AppointmentStatus::Pending,
            $tooLateAppointment
                ->fresh()
                ->status
        );

        /*
         * Exact two-hour cutoff:
         *
         * Start    = 10:00
         * Cutoff   = 120 minutes
         * Deadline = 08:00
         *
         * Current time exactly equals deadline,
         * so cancellation is allowed.
         */
        $boundaryAppointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                startsAt:
                    '10:00:00'
            );

        $this->saveRules(
            shop:
                $shop,

            cancellationCutoffMinutes:
                120
        );

        $this->patchJson(
            "/api/v1/customer/appointments/{$boundaryAppointment->id}/cancel",
            [
                'reason' =>
                    'I need to cancel at the cutoff boundary.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Cancelled
                    ->value
            );
    }

    public function test_customer_reschedule_cutoff_is_enforced(): void
    {
        [$shop, $service] =
            $this->bookableShopAndService();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $appointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                startsAt:
                    '10:00:00'
            );

        /*
         * At 08:00, a 121-minute cutoff has already
         * expired for a 10:00 appointment.
         */
        $this->saveRules(
            shop:
                $shop,

            rescheduleCutoffMinutes:
                121
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
                [
                    'requested_date' =>
                        '2026-09-14',

                    'requested_starts_at' =>
                        '13:00',
                ]
            )
            ->assertConflict();

        $this->assertDatabaseCount(
            'reschedule_requests',
            0
        );
    }

    public function test_customer_reschedule_target_must_follow_custom_slot_grid(): void
    {
        [$shop, $service] =
            $this->bookableShopAndService();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $appointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-14',

                startsAt:
                    '10:00:00'
            );

        $this->saveRules(
            shop:
                $shop,

            slotInterval:
                30
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
                [
                    'requested_date' =>
                        '2026-09-14',

                    /*
                     * 13:15 is off a 30-minute grid.
                     */
                    'requested_starts_at' =>
                        '13:15',
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

    public function test_customer_reschedule_target_obeys_custom_minimum_lead_time(): void
    {
        [$shop, $service] =
            $this->bookableShopAndService();

        $customer =
            User::factory()
                ->customer()
                ->create();

        /*
         * Original appointment remains safely in the future.
         */
        $appointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-14',

                startsAt:
                    '10:00:00'
            );

        $this->saveRules(
            shop:
                $shop,

            leadMinutes:
                120
        );

        /*
         * Current time is 08:00.
         * 09:30 is only 90 minutes away.
         */
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
                        '09:30',
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

    public function test_customer_reschedule_target_obeys_custom_booking_window(): void
    {
        [$shop, $service] =
            $this->bookableShopAndService();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $appointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                startsAt:
                    '11:00:00'
            );

        $this->saveRules(
            shop:
                $shop,

            bookingWindowDays:
                2
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
                [
                    'requested_date' =>
                        '2026-09-14',

                    'requested_starts_at' =>
                        '13:00',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_date',
            ]);
    }

    public function test_shop_owner_approval_rechecks_current_booking_rules(): void
    {
        [
            $shop,
            $service,
            $owner,
        ] =
            $this->bookableShopAndService(
                withOwner:
                    true
            );

        $customer =
            User::factory()
                ->customer()
                ->create();

        $appointment =
            $this->appointment(
                customer:
                    $customer,

                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-14',

                startsAt:
                    '10:00:00',

                status:
                    AppointmentStatus::Confirmed
            );

        /*
         * Simulate a request that was submitted while the
         * previous booking rules allowed a 13:15 appointment.
         */
        $reschedule =
            RescheduleRequest::factory()
                ->create([
                    'appointment_id' =>
                        $appointment->id,

                    'requested_date' =>
                        '2026-09-14',

                    'requested_starts_at' =>
                        '13:15:00',

                    'requested_ends_at' =>
                        '14:15:00',

                    'status' =>
                        RescheduleRequestStatus::Pending,
                ]);

        /*
         * Owner later changes interval to 30 minutes.
         *
         * The old pending request must be
         * revalidated when approved.
         */
        $this->saveRules(
            shop:
                $shop,

            slotInterval:
                30
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/reschedule-requests/{$reschedule->id}/approve"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_starts_at',
            ]);

        $appointment->refresh();

        $reschedule->refresh();

        $this->assertSame(
            '10:00:00',
            $appointment->starts_at
        );

        $this->assertSame(
            RescheduleRequestStatus::Pending,
            $reschedule->status
        );
    }

    public function test_rule_configuration_is_restored_between_shops(): void
    {
        [
            $customShop,
            $customService,
        ] =
            $this->bookableShopAndService();

        [
            $defaultShop,
            $defaultService,
        ] =
            $this->bookableShopAndService();

        $this->saveRules(
            shop:
                $customShop,

            slotInterval:
                30
        );

        $customer =
            User::factory()
                ->customer()
                ->create();

        /*
         * Custom Shop = 30 minutes.
         */
        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$customShop->id}/services/{$customService->id}/available-slots?date=2026-09-07"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.slot_interval_minutes',
                30
            );

        /*
         * Second shop has no row and must
         * return to global/default 15.
         */
        $this->getJson(
            "/api/v1/customer/shops/{$defaultShop->id}/services/{$defaultService->id}/available-slots?date=2026-09-07"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.slot_interval_minutes',
                15
            );
    }

    /**
     * @return array{0: Shop, 1: Service, 2?: User}
     */
    private function bookableShopAndService(
        bool $withOwner = false
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

                    'availability_status' =>
                        ShopAvailabilityStatus::Open,
                ]);

        ShopSchedule::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->openBetween(
                '09:00:00',
                '18:00:00'
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

        if ($withOwner) {
            return [
                $shop,
                $service,
                $owner,
            ];
        }

        return [
            $shop,
            $service,
        ];
    }

    private function saveRules(
        Shop $shop,
        int $slotInterval = 15,
        int $leadMinutes = 0,
        int $bookingWindowDays = 60,
        int $cancellationCutoffMinutes = 0,
        int $rescheduleCutoffMinutes = 0
    ): ShopBookingRule {
        ShopBookingRule::query()
            ->where(
                'shop_id',
                $shop->id
            )
            ->delete();

        $rules =
            new ShopBookingRule([
                'slot_interval_minutes' =>
                    $slotInterval,

                'minimum_lead_time_minutes' =>
                    $leadMinutes,

                'booking_window_days' =>
                    $bookingWindowDays,

                'cancellation_cutoff_minutes' =>
                    $cancellationCutoffMinutes,

                'reschedule_cutoff_minutes' =>
                    $rescheduleCutoffMinutes,
            ]);

        /*
         * Server-controlled ownership.
         */
        $rules->shop_id =
            $shop->id;

        $rules->save();

        return $rules;
    }

    private function appointment(
        User $customer,
        Shop $shop,
        Service $service,
        string $date = '2026-09-07',
        string $startsAt = '10:00:00',
        AppointmentStatus $status = AppointmentStatus::Pending
    ): Appointment {
        $start =
            CarbonImmutable::parse(
                $date
                .' '
                .$startsAt,
                'Asia/Manila'
            );

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
                    $start->format(
                        'H:i:s'
                    ),

                'ends_at' =>
                    $start
                        ->addMinutes(
                            60
                        )
                        ->format(
                            'H:i:s'
                        ),

                'service_name' =>
                    $service->name,

                'price_snapshot' =>
                    $service->price,

                'duration_minutes_snapshot' =>
                    60,

                'status' =>
                    $status,
            ]);
    }
}