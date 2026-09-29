<?php

namespace Tests\Feature\Api\V1\ShopOwner;

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

class RescheduleRequestManagementApiTest extends TestCase
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

    public function test_reschedule_review_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/reschedule-requests'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_use_shop_owner_reschedule_routes(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/reschedule-requests'
        )->assertForbidden();
    }

    public function test_admin_cannot_use_shop_owner_reschedule_routes(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/reschedule-requests'
        )->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_review_reschedule_requests(): void
    {
        $owner = User::factory()
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
            '/api/v1/shop-owner/reschedule-requests'
        )->assertForbidden();
    }

    public function test_owner_lists_only_own_shop_requests(): void
    {
        [$owner, $appointment] =
            $this->environment();

        [, $otherAppointment] =
            $this->environment();

        RescheduleRequest::factory()->create([
            'appointment_id' =>
                $appointment->id,
        ]);

        RescheduleRequest::factory()->create([
            'appointment_id' =>
                $otherAppointment->id,
        ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/reschedule-requests'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.reschedule_requests'
            );
    }

    public function test_owner_cannot_view_another_shops_request(): void
    {
        [$owner] =
            $this->environment();

        [, $otherAppointment] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $otherAppointment
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}"
        )->assertNotFound();
    }

    public function test_owner_can_approve_valid_pending_request(): void
    {
        [$owner, $appointment] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.reschedule_request.status',
                RescheduleRequestStatus::Approved
                    ->value
            )
            ->assertJsonPath(
                'data.reschedule_request.appointment.appointment_date',
                '2026-08-24'
            )
            ->assertJsonPath(
                'data.reschedule_request.appointment.starts_at',
                '13:00:00'
            )
            ->assertJsonPath(
                'data.reschedule_request.appointment.ends_at',
                '14:00:00'
            );

        $updatedAppointment =
            $appointment->fresh();

        $updatedRequest =
            $request->fresh();

        $this->assertSame(
            '13:00:00',
            $updatedAppointment->starts_at
        );

        $this->assertSame(
            '14:00:00',
            $updatedAppointment->ends_at
        );

        $this->assertSame(
            AppointmentStatus::Pending,
            $updatedAppointment->status
        );

        $this->assertSame(
            RescheduleRequestStatus::Approved,
            $updatedRequest->status
        );

        $this->assertSame(
            $owner->id,
            $updatedRequest->reviewed_by
        );

        $this->assertNotNull(
            $updatedRequest->reviewed_at
        );
    }

    public function test_approval_preserves_confirmed_appointment_status(): void
    {
        [$owner, $appointment] =
            $this->environment(
                AppointmentStatus::Confirmed
            );

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
        )->assertOk();

        $this->assertSame(
            AppointmentStatus::Confirmed,
            $appointment->fresh()->status
        );
    }

    public function test_owner_cannot_approve_another_shops_request(): void
    {
        [$owner] =
            $this->environment();

        [, $otherAppointment] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $otherAppointment
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
        )->assertNotFound();

        $this->assertSame(
            RescheduleRequestStatus::Pending,
            $request->fresh()->status
        );
    }

    public function test_already_reviewed_request_cannot_be_approved_again(): void
    {
        [$owner, $appointment] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $appointment,
                RescheduleRequestStatus::Rejected
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
        )->assertConflict();
    }

    public function test_approval_rechecks_new_shop_conflict(): void
    {
        [$owner, $appointment, $shop, $service] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $appointment
            );

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
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_starts_at',
            ]);

        $this->assertSame(
            '10:00:00',
            $appointment->fresh()->starts_at
        );

        $this->assertSame(
            RescheduleRequestStatus::Pending,
            $request->fresh()->status
        );
    }

    public function test_approval_rechecks_customer_conflict_at_other_shop(): void
    {
        [$owner, $appointment] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        $otherShop =
            Shop::factory()
                ->approved()
                ->create([
                    'availability_status' =>
                        ShopAvailabilityStatus::Open,
                ]);

        $otherService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $otherShop->id,
                ]);

        Appointment::factory()->create([
            'customer_id' =>
                $appointment->customer_id,

            'shop_id' =>
                $otherShop->id,

            'service_id' =>
                $otherService->id,

            'appointment_date' =>
                '2026-08-24',

            'starts_at' =>
                '13:30:00',

            'ends_at' =>
                '14:30:00',

            'service_name' =>
                $otherService->name,

            'price_snapshot' =>
                $otherService->price,

            'duration_minutes_snapshot' =>
                $otherService
                    ->duration_minutes,

            'status' =>
                AppointmentStatus::Confirmed,
        ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_starts_at',
            ]);
    }

    public function test_approval_rechecks_shop_schedule(): void
    {
        [$owner, $appointment, $shop] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        $shop->schedules()
            ->where(
                'day_of_week',
                DayOfWeek::Monday->value
            )
            ->update([
                'is_open' => false,
                'opens_at' => null,
                'closes_at' => null,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_date',
            ]);
    }

    public function test_approval_rechecks_shop_availability(): void
    {
        [$owner, $appointment, $shop] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        $shop->availability_status =
            ShopAvailabilityStatus::FullyBooked;

        $shop->save();

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_date',
            ]);
    }

    public function test_past_requested_slot_cannot_be_approved(): void
    {
        [$owner, $appointment] =
            $this->environment();

        $request =
            RescheduleRequest::factory()
                ->create([
                    'appointment_id' =>
                        $appointment->id,

                    'requested_date' =>
                        '2026-08-19',

                    'requested_starts_at' =>
                        '13:00:00',

                    'requested_ends_at' =>
                        '14:00:00',

                    'status' =>
                        RescheduleRequestStatus::Pending,
                ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/approve"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requested_starts_at',
            ]);
    }

    public function test_owner_can_reject_pending_request_with_reason(): void
    {
        [$owner, $appointment] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/reject",
            [
                'reason' =>
                    'The requested slot is unavailable.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.reschedule_request.status',
                RescheduleRequestStatus::Rejected
                    ->value
            );

        $updated =
            $request->fresh();

        $this->assertSame(
            RescheduleRequestStatus::Rejected,
            $updated->status
        );

        $this->assertSame(
            'The requested slot is unavailable.',
            $updated->rejection_reason
        );

        $this->assertSame(
            $owner->id,
            $updated->reviewed_by
        );

        $this->assertNotNull(
            $updated->reviewed_at
        );

        /*
         * Rejecting the request must not alter the appointment.
         */
        $this->assertSame(
            '10:00:00',
            $appointment->fresh()->starts_at
        );
    }

    public function test_rejection_requires_meaningful_reason(): void
    {
        [$owner, $appointment] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/reject",
            [
                'reason' => 'No',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);
    }

    public function test_owner_cannot_reject_another_shops_request(): void
    {
        [$owner] =
            $this->environment();

        [, $otherAppointment] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $otherAppointment
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/reject",
            [
                'reason' =>
                    'Unauthorized review attempt.',
            ]
        )->assertNotFound();

        $this->assertSame(
            RescheduleRequestStatus::Pending,
            $request->fresh()->status
        );
    }

    public function test_approved_request_cannot_be_rejected_later(): void
    {
        [$owner, $appointment] =
            $this->environment();

        $request =
            $this->rescheduleRequest(
                $appointment,
                RescheduleRequestStatus::Approved
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            "/api/v1/shop-owner/reschedule-requests/{$request->id}/reject",
            [
                'reason' =>
                    'Attempting another review.',
            ]
        )->assertConflict();
    }

    /**
     * @return array{
     *     0: User,
     *     1: Appointment,
     *     2: Shop,
     *     3: Service
     * }
     */
    private function environment(
        AppointmentStatus $status =
            AppointmentStatus::Pending
    ): array {
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
            $owner,
            $appointment,
            $shop,
            $service,
        ];
    }

    private function rescheduleRequest(
        Appointment $appointment,
        RescheduleRequestStatus $status =
            RescheduleRequestStatus::Pending
    ): RescheduleRequest {
        return RescheduleRequest::factory()
            ->create([
                'appointment_id' =>
                    $appointment->id,

                'requested_date' =>
                    '2026-08-24',

                'requested_starts_at' =>
                    '13:00:00',

                'requested_ends_at' =>
                    '14:00:00',

                'status' =>
                    $status,
            ]);
    }
}