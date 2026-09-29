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
use App\Models\ShopSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentRescheduleApiTest extends TestCase
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

    public function test_customer_can_cancel_pending_future_appointment(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            "/api/v1/customer/appointments/{$appointment->id}/cancel",
            [
                'reason' =>
                    'My schedule has changed.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Cancelled
                    ->value
            );

        $updated =
            $appointment->fresh();

        $this->assertSame(
            AppointmentStatus::Cancelled,
            $updated->status
        );

        $this->assertSame(
            $customer->id,
            $updated->cancelled_by
        );

        $this->assertNotNull(
            $updated->cancelled_at
        );
    }

    public function test_customer_can_cancel_confirmed_future_appointment(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment(
                AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            "/api/v1/customer/appointments/{$appointment->id}/cancel",
            [
                'reason' =>
                    'I cannot attend anymore.',
            ]
        )->assertOk();

        $this->assertSame(
            AppointmentStatus::Cancelled,
            $appointment->fresh()->status
        );
    }

    public function test_customer_cannot_cancel_another_customers_appointment(): void
    {
        [, $appointment] =
            $this->bookableAppointment();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $otherCustomer,
            'sanctum'
        )->patchJson(
            "/api/v1/customer/appointments/{$appointment->id}/cancel",
            [
                'reason' =>
                    'Unauthorized cancellation.',
            ]
        )->assertNotFound();

        $this->assertSame(
            AppointmentStatus::Pending,
            $appointment->fresh()->status
        );
    }

    public function test_cancellation_requires_reason(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            "/api/v1/customer/appointments/{$appointment->id}/cancel",
            [
                'reason' => 'No',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);
    }

    public function test_completed_appointment_cannot_be_cancelled(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment(
                AppointmentStatus::Completed
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            "/api/v1/customer/appointments/{$appointment->id}/cancel",
            [
                'reason' =>
                    'Attempting invalid cancellation.',
            ]
        )->assertConflict();
    }

    public function test_customer_can_request_valid_reschedule(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
                [
                    'requested_date' =>
                        '2026-08-24',

                    'requested_starts_at' =>
                        '13:00',

                    'reason' =>
                        'I am available later.',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.reschedule_request.status',
                RescheduleRequestStatus::Pending
                    ->value
            )
            ->assertJsonPath(
                'data.reschedule_request.requested_starts_at',
                '13:00:00'
            )
            ->assertJsonPath(
                'data.reschedule_request.requested_ends_at',
                '14:00:00'
            );

        $this->assertDatabaseHas(
            'reschedule_requests',
            [
                'appointment_id' =>
                    $appointment->id,

                'requested_date' =>
                    '2026-08-24',

                'requested_starts_at' =>
                    '13:00:00',

                'requested_ends_at' =>
                    '14:00:00',

                'status' =>
                    RescheduleRequestStatus::Pending
                        ->value,
            ]
        );
    }

    public function test_reschedule_uses_original_duration_snapshot(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        /*
         * Change the current service duration after booking.
         * The appointment snapshot remains 60 minutes.
         */
        $appointment->service
            ->update([
                'duration_minutes' => 120,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
            [
                'requested_date' =>
                    '2026-08-24',

                'requested_starts_at' =>
                    '13:00',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.reschedule_request.requested_ends_at',
                '14:00:00'
            );
    }

    public function test_duplicate_pending_reschedule_request_is_rejected(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        RescheduleRequest::factory()->create([
            'appointment_id' =>
                $appointment->id,

            'requested_date' =>
                '2026-08-24',

            'requested_starts_at' =>
                '13:00:00',

            'requested_ends_at' =>
                '14:00:00',

            'status' =>
                RescheduleRequestStatus::Pending,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
            [
                'requested_date' =>
                    '2026-08-24',

                'requested_starts_at' =>
                    '15:00',
            ]
        )->assertConflict();
    }

    public function test_customer_cannot_request_same_current_slot(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
            [
                'requested_date' =>
                    '2026-08-24',

                'requested_starts_at' =>
                    '10:00',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_starts_at',
            ]);
    }

    public function test_reschedule_to_closed_day_is_rejected(): void
    {
        [$customer, $appointment, $shop] =
            $this->bookableAppointment();

        ShopSchedule::factory()
            ->forDay(
                DayOfWeek::Tuesday
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
            "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
            [
                'requested_date' =>
                    '2026-08-25',

                'requested_starts_at' =>
                    '13:00',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_date',
            ]);
    }

    public function test_reschedule_outside_operating_hours_is_rejected(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
            [
                'requested_date' =>
                    '2026-08-24',

                'requested_starts_at' =>
                    '17:30',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_starts_at',
            ]);
    }

    public function test_reschedule_cannot_overlap_another_shop_appointment(): void
    {
        [$customer, $appointment, $shop, $service] =
            $this->bookableAppointment();

        Appointment::factory()->create([
            'shop_id' =>
                $shop->id,

            'service_id' =>
                $service->id,

            'appointment_date' =>
                '2026-08-24',

            'starts_at' =>
                '13:30:00',

            'ends_at' =>
                '14:30:00',

            'service_name' =>
                $service->name,

            'price_snapshot' =>
                $service->price,

            'duration_minutes_snapshot' =>
                60,

            'status' =>
                AppointmentStatus::Confirmed,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
            [
                'requested_date' =>
                    '2026-08-24',

                'requested_starts_at' =>
                    '13:00',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_starts_at',
            ]);
    }

    public function test_terminal_appointment_cannot_be_rescheduled(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment(
                AppointmentStatus::Cancelled
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            "/api/v1/customer/appointments/{$appointment->id}/reschedule-requests",
            [
                'requested_date' =>
                    '2026-08-24',

                'requested_starts_at' =>
                    '13:00',
            ]
        )->assertConflict();
    }

    public function test_customer_lists_only_own_reschedule_requests(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        [, $otherAppointment] =
            $this->bookableAppointment();

        RescheduleRequest::factory()->create([
            'appointment_id' =>
                $appointment->id,
        ]);

        RescheduleRequest::factory()->create([
            'appointment_id' =>
                $otherAppointment->id,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/reschedule-requests'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.reschedule_requests'
            );
    }

    public function test_customer_can_withdraw_pending_reschedule_request(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        $request =
            RescheduleRequest::factory()
                ->create([
                    'appointment_id' =>
                        $appointment->id,

                    'status' =>
                        RescheduleRequestStatus::Pending,
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            "/api/v1/customer/reschedule-requests/{$request->id}/withdraw"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.reschedule_request.status',
                RescheduleRequestStatus::Withdrawn
                    ->value
            );

        $this->assertSame(
            RescheduleRequestStatus::Withdrawn,
            $request->fresh()->status
        );
    }

    public function test_customer_cannot_withdraw_another_customers_request(): void
    {
        [, $appointment] =
            $this->bookableAppointment();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        $request =
            RescheduleRequest::factory()
                ->create([
                    'appointment_id' =>
                        $appointment->id,
                ]);

        $this->actingAs(
            $otherCustomer,
            'sanctum'
        )->patchJson(
            "/api/v1/customer/reschedule-requests/{$request->id}/withdraw"
        )->assertNotFound();

        $this->assertSame(
            RescheduleRequestStatus::Pending,
            $request->fresh()->status
        );
    }

    public function test_cancelling_appointment_withdraws_pending_reschedule_request(): void
    {
        [$customer, $appointment] =
            $this->bookableAppointment();

        $request =
            RescheduleRequest::factory()
                ->create([
                    'appointment_id' =>
                        $appointment->id,

                    'status' =>
                        RescheduleRequestStatus::Pending,
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            "/api/v1/customer/appointments/{$appointment->id}/cancel",
            [
                'reason' =>
                    'I can no longer attend.',
            ]
        )->assertOk();

        $this->assertSame(
            RescheduleRequestStatus::Withdrawn,
            $request->fresh()->status
        );
    }

    /**
     * @return array{
     *     0: User,
     *     1: Appointment,
     *     2: Shop,
     *     3: Service
     * }
     */
    private function bookableAppointment(
        AppointmentStatus $status =
            AppointmentStatus::Pending
    ): array {
        $customer =
            User::factory()
                ->customer()
                ->create();

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

        $appointment =
            Appointment::factory()
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
                        '10:00:00',

                    'ends_at' =>
                        '11:00:00',

                    'service_name' =>
                        $service->name,

                    'price_snapshot' =>
                        $service->price,

                    'duration_minutes_snapshot' =>
                        60,

                    'status' =>
                        $status,
                ]);

        return [
            $customer,
            $appointment,
            $shop,
            $service,
        ];
    }
}