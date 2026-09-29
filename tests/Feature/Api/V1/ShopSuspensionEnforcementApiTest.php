<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\ShopAdministrativeStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSuspensionEnforcementApiTest extends TestCase
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
                    'Asia/Manila',
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_suspended_shop_is_hidden_from_customer_discovery_and_detail(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->suspended(
                    'Repeated marketplace policy violations.'
                )
                ->create([
                    'name' =>
                        'Suspended Hidden Shop',
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops?search=Suspended%20Hidden%20Shop'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.shops'
            );

        $this->getJson(
            "/api/v1/customer/shops/{$shop->id}"
        )->assertNotFound();
    }

    public function test_suspended_shop_has_no_customer_availability(): void
    {
        [
            $shop,
            $service,
        ] =
            $this->suspendedBookableShopAndService();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-dates?days=7"
            )
            ->assertNotFound();

        $this->getJson(
            "/api/v1/customer/shops/{$shop->id}/services/{$service->id}/available-slots?date=2026-09-14"
        )
            ->assertNotFound();
    }

    public function test_customer_cannot_directly_book_suspended_shop(): void
    {
        [
            $shop,
            $service,
        ] =
            $this->suspendedBookableShopAndService();

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
                        '2026-09-14',

                    'starts_at' =>
                        '09:00',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shop_id',
            ]);

        $this->assertDatabaseCount(
            'appointments',
            0
        );
    }

    public function test_customer_cannot_create_reschedule_request_for_suspended_shop(): void
    {
        [
            $shop,
            $service,
        ] =
            $this->suspendedBookableShopAndService();

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
                        '11:00',

                    'reason' =>
                        'I need a later appointment.',
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

    public function test_existing_customer_appointment_history_remains_accessible_while_shop_is_suspended(): void
    {
        [
            $shop,
            $service,
        ] =
            $this->suspendedBookableShopAndService();

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
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/appointments'
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' =>
                    $appointment->id,
            ]);

        $this->getJson(
            "/api/v1/customer/appointments/{$appointment->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.id',
                $appointment->id
            );
    }

    public function test_customer_can_cancel_existing_future_appointment_while_shop_is_suspended(): void
    {
        [
            $shop,
            $service,
        ] =
            $this->suspendedBookableShopAndService();

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

                status:
                    AppointmentStatus::Confirmed,
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/customer/appointments/{$appointment->id}/cancel",
                [
                    'reason' =>
                        'I no longer want to keep this booking.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Cancelled
                    ->value
            );

        $this->assertSame(
            AppointmentStatus::Cancelled,
            $appointment
                ->fresh()
                ->status
        );
    }

    public function test_suspended_shop_owner_can_still_login_and_view_suspension_state(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->suspended(
                    'Temporary suspension pending administrative review.'
                )
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $login =
            $this->postJson(
                '/api/v1/auth/shop-owners/login',
                [
                    'email' =>
                        $owner->email,

                    'password' =>
                        'password',
                ]
            )
                ->assertOk()
                ->assertJsonPath(
                    'data.user.status',
                    UserStatus::Active
                        ->value
                );

        $token =
            $login->json(
                'data.token'
            );

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.shop.approval_status',
                'approved'
            )
            ->assertJsonPath(
                'data.shop.administrative_status',
                ShopAdministrativeStatus::Suspended
                    ->value
            )
            ->assertJsonPath(
                'data.shop.suspension.is_suspended',
                true
            )
            ->assertJsonPath(
                'data.shop.suspension.reason',
                'Temporary suspension pending administrative review.'
            );
    }

    public function test_suspended_shop_owner_is_blocked_from_shop_management_apis(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()
            ->approved()
            ->suspended()
            ->create([
                'owner_id' =>
                    $owner->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        );

        foreach (
            [
                '/api/v1/shop-owner/services',
                '/api/v1/shop-owner/analytics',
                '/api/v1/shop-owner/booking-rules',
            ] as $endpoint
        ) {
            $this->getJson(
                $endpoint
            )
                ->assertForbidden()
                ->assertJsonPath(
                    'message',
                    'Your shop is currently suspended by an administrator. Shop management is unavailable until the suspension is lifted.'
                );
        }
    }

    public function test_reactivation_restores_customer_visibility_and_shop_owner_management(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->suspended()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $shop->forceFill([
            'administrative_status' =>
                ShopAdministrativeStatus::Active,

            'suspended_by' =>
                null,

            'suspended_at' =>
                null,

            'suspension_reason' =>
                null,
        ])->save();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertOk();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/services'
            )
            ->assertOk();
    }

    /**
     * @return array{0: Shop, 1: Service}
     */
    private function suspendedBookableShopAndService(): array
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->suspended(
                    'Temporary administrative suspension.'
                )
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
                        'Suspended Shop Haircut',

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

    private function appointment(
        User $customer,
        Shop $shop,
        Service $service,
        AppointmentStatus $status = AppointmentStatus::Pending
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
                    '2026-09-14',

                'starts_at' =>
                    '10:00:00',

                'ends_at' =>
                    '11:00:00',

                'service_name' =>
                    $service->name,

                'price_snapshot' =>
                    '500.00',

                'duration_minutes_snapshot' =>
                    60,

                'status' =>
                    $status,
            ]);
    }
}