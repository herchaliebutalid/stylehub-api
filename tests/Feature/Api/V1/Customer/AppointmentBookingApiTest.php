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

class AppointmentBookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            '2026-08-20 08:00:00'
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_booking_requires_authentication(): void
    {
        $this->postJson(
            '/api/v1/customer/appointments',
            [
                'shop_id' => 1,
                'service_id' => 1,
                'appointment_date' =>
                    '2026-08-24',
                'starts_at' =>
                    '10:00',
            ]
        )->assertUnauthorized();
    }

    public function test_shop_owner_cannot_use_customer_booking_route(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            [
                'shop_id' => 1,
                'service_id' => 1,
                'appointment_date' =>
                    '2026-08-24',
                'starts_at' =>
                    '10:00',
            ]
        )->assertForbidden();
    }

    public function test_admin_cannot_use_customer_booking_route(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            [
                'shop_id' => 1,
                'service_id' => 1,
                'appointment_date' =>
                    '2026-08-24',
                'starts_at' =>
                    '10:00',
            ]
        )->assertForbidden();
    }

    public function test_customer_can_book_valid_appointment(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $response = $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $this->validPayload(
                $shop,
                $service
            )
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Pending
                    ->value
            )
            ->assertJsonPath(
                'data.appointment.starts_at',
                '10:00:00'
            )
            ->assertJsonPath(
                'data.appointment.ends_at',
                '11:00:00'
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
                    '2026-08-24',

                'starts_at' =>
                    '10:00:00',

                'ends_at' =>
                    '11:00:00',

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

    public function test_customer_cannot_inject_appointment_status(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $payload = $this->validPayload(
            $shop,
            $service
        );

        $payload['status'] =
            AppointmentStatus::Confirmed->value;

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $payload
        )->assertUnprocessable();

        $this->assertDatabaseCount(
            'appointments',
            0
        );
    }

    public function test_pending_shop_cannot_receive_booking(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $shop = Shop::factory()->create([
            'availability_status' =>
                ShopAvailabilityStatus::Open,
        ]);

        $service =
            Service::factory()->create([
                'shop_id' => $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $this->validPayload(
                $shop,
                $service
            )
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shop_id',
            ]);
    }

    public function test_fully_booked_shop_rejects_booking(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $shop->availability_status =
            ShopAvailabilityStatus::FullyBooked;

        $shop->save();

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $this->validPayload(
                $shop,
                $service
            )
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shop_id',
            ]);
    }

    public function test_inactive_service_cannot_be_booked(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $service->is_active = false;
        $service->save();

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $this->validPayload(
                $shop,
                $service
            )
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'service_id',
            ]);
    }

    public function test_service_from_another_shop_cannot_be_injected(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop] =
            $this->bookableShopAndService();

        [, $otherService] =
            $this->bookableShopAndService();

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            [
                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $otherService->id,

                'appointment_date' =>
                    '2026-08-24',

                'starts_at' =>
                    '10:00',

                'notes' =>
                    null,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'service_id',
            ]);
    }

    public function test_customer_cannot_book_when_shop_is_closed_that_day(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $shop->schedules()
            ->delete();

        ShopSchedule::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->closed()
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $this->validPayload(
                $shop,
                $service
            )
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'appointment_date',
            ]);
    }

    public function test_customer_cannot_book_before_opening_time(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $payload = $this->validPayload(
            $shop,
            $service
        );

        $payload['starts_at'] =
            '08:30';

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'starts_at',
            ]);
    }

    public function test_service_must_finish_before_shop_closes(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $payload = $this->validPayload(
            $shop,
            $service
        );

        $payload['starts_at'] =
            '17:30';

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'starts_at',
            ]);
    }

    public function test_past_appointment_time_is_rejected(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService(
                day: DayOfWeek::Thursday
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            [
                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $service->id,

                'appointment_date' =>
                    '2026-08-20',

                'starts_at' =>
                    '07:00',

                'notes' =>
                    null,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'starts_at',
            ]);
    }

    public function test_overlapping_pending_appointment_is_rejected(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $this->createAppointment(
            shop: $shop,
            service: $service,
            startsAt: '10:00:00',
            endsAt: '11:00:00',
            status: AppointmentStatus::Pending,
        );

        $payload = $this->validPayload(
            $shop,
            $service
        );

        $payload['starts_at'] =
            '10:30';

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'starts_at',
            ]);
    }

    public function test_appointment_ending_at_existing_start_is_allowed(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $this->createAppointment(
            shop: $shop,
            service: $service,
            startsAt: '11:00:00',
            endsAt: '12:00:00',
            status: AppointmentStatus::Confirmed,
        );

        $payload = $this->validPayload(
            $shop,
            $service
        );

        $payload['starts_at'] =
            '10:00';

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $payload
        )->assertCreated();
    }

    public function test_cancelled_appointment_does_not_block_slot(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $this->createAppointment(
            shop: $shop,
            service: $service,
            startsAt: '10:00:00',
            endsAt: '11:00:00',
            status: AppointmentStatus::Cancelled,
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $this->validPayload(
                $shop,
                $service
            )
        )->assertCreated();
    }

    public function test_customer_cannot_have_overlapping_appointments_at_different_shops(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [$firstShop, $firstService] =
            $this->bookableShopAndService();

        [$secondShop, $secondService] =
            $this->bookableShopAndService();

        $this->createAppointment(
            shop: $firstShop,
            service: $firstService,
            startsAt: '10:00:00',
            endsAt: '11:00:00',
            status: AppointmentStatus::Confirmed,
            customer: $customer,
        );

        $payload = $this->validPayload(
            $secondShop,
            $secondService
        );

        $payload['starts_at'] =
            '10:30';

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/customer/appointments',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'starts_at',
            ]);
    }

    public function test_customer_lists_only_own_appointments(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $otherCustomer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $this->createAppointment(
            shop: $shop,
            service: $service,
            startsAt: '10:00:00',
            endsAt: '11:00:00',
            customer: $customer,
        );

        $this->createAppointment(
            shop: $shop,
            service: $service,
            startsAt: '12:00:00',
            endsAt: '13:00:00',
            customer: $otherCustomer,
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/appointments'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.appointments'
            );
    }

    public function test_customer_cannot_view_another_customers_appointment(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $otherCustomer = User::factory()
            ->customer()
            ->create();

        [$shop, $service] =
            $this->bookableShopAndService();

        $appointment =
            $this->createAppointment(
                shop: $shop,
                service: $service,
                startsAt: '10:00:00',
                endsAt: '11:00:00',
                customer: $otherCustomer,
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/appointments/{$appointment->id}"
            )
            ->assertNotFound();
    }

    /**
     * Create an approved and bookable shop with one active service.
     *
     * @return array{0: Shop, 1: Service}
     */
    private function bookableShopAndService(
        DayOfWeek $day = DayOfWeek::Monday
    ): array {
        $shop = Shop::factory()
            ->approved()
            ->create([
                'availability_status' =>
                    ShopAvailabilityStatus::Open,
            ]);

        ShopSchedule::factory()
            ->forDay($day)
            ->openBetween(
                '09:00:00',
                '18:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $service =
            Service::factory()->create([
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
            $shop,
            $service,
        ];
    }

    /**
     * Build a valid customer booking payload.
     *
     * @return array<string, mixed>
     */
    private function validPayload(
        Shop $shop,
        Service $service
    ): array {
        return [
            'shop_id' =>
                $shop->id,

            'service_id' =>
                $service->id,

            'appointment_date' =>
                '2026-08-24',

            'starts_at' =>
                '10:00',

            'notes' =>
                'Please use a low fade.',
        ];
    }

    private function createAppointment(
        Shop $shop,
        Service $service,
        string $startsAt,
        string $endsAt,
        AppointmentStatus $status =
            AppointmentStatus::Pending,
        ?User $customer = null
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
                    '2026-08-24',

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