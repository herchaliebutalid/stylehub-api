<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\ShopAvailabilityStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopSchedule;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StyleHubCrossPlatformWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent real SMS or queue processing.
        Queue::fake();
        config()->set('sms.enabled', false);

        // Fixed date for repeatable testing.
        CarbonImmutable::setTestNow(
            '2026-08-20 08:00:00'
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_booking_flows_from_customer_to_shop_owner_to_admin_and_back(): void
    {
        // Create test accounts.
        $customer = User::factory()
            ->customer()
            ->create();

        $owner = User::factory()
            ->shopOwner()
            ->create();

        $admin = User::factory()
            ->admin()
            ->create();

        // Create an approved shop.
        $shop = Shop::factory()
            ->approved()
            ->create([
                'owner_id' => $owner->id,
                'availability_status' =>
                    ShopAvailabilityStatus::Open,
            ]);

        // August 24, 2026 is Monday.
        ShopSchedule::factory()
            ->forDay(DayOfWeek::Monday)
            ->openBetween(
                '09:00:00',
                '18:00:00'
            )
            ->create([
                'shop_id' => $shop->id,
            ]);

        $service = Service::factory()
            ->create([
                'shop_id' => $shop->id,
                'name' => 'Premium Haircut',
                'price' => '500.00',
                'duration_minutes' => 60,
                'is_active' => true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | TEST 1: Customer creates an appointment
        |--------------------------------------------------------------------------
        */

        $booking = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                '/api/v1/customer/appointments',
                [
                    'shop_id' => $shop->id,
                    'service_id' => $service->id,
                    'appointment_date' =>
                        '2026-08-24',
                    'starts_at' => '10:00',
                    'notes' =>
                        'Cross-platform test booking',
                ]
            );

        $booking
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.appointment.status',
                'pending'
            )
            ->assertJsonPath(
                'data.appointment.starts_at',
                '10:00:00'
            );

        $appointmentId = (int) $booking->json(
            'data.appointment.id'
        );

        $appointment = Appointment::query()
            ->findOrFail($appointmentId);

        $this->assertDatabaseHas(
            'appointments',
            [
                'id' => $appointmentId,
                'customer_id' => $customer->id,
                'shop_id' => $shop->id,
                'status' =>
                    AppointmentStatus::Pending->value,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | TEST 2: Shop owner sees customer appointment
        |--------------------------------------------------------------------------
        */

        $this->actingAs($owner, 'sanctum')
            ->getJson(
                "/api/v1/shop-owner/appointments/{$appointmentId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                'pending'
            );

        /*
        |--------------------------------------------------------------------------
        | TEST 3: Admin sees the same appointment
        |--------------------------------------------------------------------------
        */

        $this->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/v1/admin/appointments/{$appointmentId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                'pending'
            )
            ->assertJsonPath(
                'data.appointment.start_time',
                '10:00:00'
            );

        /*
        |--------------------------------------------------------------------------
        | TEST 4: Shop owner confirms the appointment
        |--------------------------------------------------------------------------
        */

        $this->actingAs($owner, 'sanctum')
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointmentId}/confirm"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                'confirmed'
            );

        $this->assertSame(
            AppointmentStatus::Confirmed,
            $appointment->fresh()->status
        );

        /*
        |--------------------------------------------------------------------------
        | TEST 5: Customer sees confirmed status
        |--------------------------------------------------------------------------
        */

        $this->actingAs($customer, 'sanctum')
            ->getJson(
                "/api/v1/customer/appointments/{$appointmentId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                'confirmed'
            );

        /*
        |--------------------------------------------------------------------------
        | TEST 6: Admin sees confirmed status
        |--------------------------------------------------------------------------
        */

        $this->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/v1/admin/appointments/{$appointmentId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                'confirmed'
            );

        /*
        |--------------------------------------------------------------------------
        | TEST 7: In-app notification API
        |--------------------------------------------------------------------------
        |
        | RefreshDatabase wraps this test in a transaction,
        | delaying production after-commit observers.
        |
        | Invoke the notification service explicitly to
        | check notification storage and API visibility.
        |
        */

        $notifications = app(
            InAppNotificationService::class
        );

        $notifications->bookingReceived(
            $appointment
        );

        $notifications->appointmentConfirmed(
            $appointment->fresh()
        );

        // Shop owner sees booking notification.
        $this->actingAs($owner, 'sanctum')
            ->getJson(
                '/api/v1/notifications?status=unread'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.notifications.0.type',
                'booking_received'
            );

        // Customer sees confirmation notification.
        $this->actingAs($customer, 'sanctum')
            ->getJson(
                '/api/v1/notifications?status=unread'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.notifications.0.type',
                'appointment_confirmed'
            );

        /*
        |--------------------------------------------------------------------------
        | TEST 8: Shop owner completes appointment
        |--------------------------------------------------------------------------
        */

        // Move simulated time beyond appointment end.
        CarbonImmutable::setTestNow(
            '2026-08-24 12:00:00'
        );

        $this->actingAs($owner, 'sanctum')
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointmentId}/complete"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                'completed'
            );

        /*
        |--------------------------------------------------------------------------
        | TEST 9: Customer submits feedback
        |--------------------------------------------------------------------------
        */

        $feedback = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                "/api/v1/customer/appointments/{$appointmentId}/feedback",
                [
                    'rating' => 5,
                    'comment' =>
                        'Excellent service.',
                ]
            );

        $feedback
            ->assertCreated()
            ->assertJsonPath(
                'data.feedback.rating',
                5
            );

        $feedbackId = (int) $feedback->json(
            'data.feedback.id'
        );

        /*
        |--------------------------------------------------------------------------
        | TEST 10: Admin sees feedback and appointment time
        |--------------------------------------------------------------------------
        */

        $this->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/v1/admin/feedback/{$feedbackId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.feedback.rating',
                5
            )
            ->assertJsonPath(
                'data.feedback.appointment.id',
                $appointmentId
            )
            ->assertJsonPath(
                'data.feedback.appointment.start_time',
                '10:00:00'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 11: Role-based API security
    |--------------------------------------------------------------------------
    */

    public function test_roles_cannot_access_one_anothers_protected_appointments(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $owner = User::factory()
            ->shopOwner()
            ->create();

        $admin = User::factory()
            ->admin()
            ->create();

        // Guest cannot access admin appointments.
        $this->getJson(
            '/api/v1/admin/appointments'
        )->assertUnauthorized();

        // Customer cannot access admin appointments.
        $this->actingAs($customer, 'sanctum')
            ->getJson(
                '/api/v1/admin/appointments'
            )
            ->assertForbidden();

        // Shop owner cannot access admin appointments.
        $this->actingAs($owner, 'sanctum')
            ->getJson(
                '/api/v1/admin/appointments'
            )
            ->assertForbidden();

        // Admin cannot manage shop-owner appointments.
        $this->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/v1/shop-owner/appointments'
            )
            ->assertForbidden();

        // Admin cannot use customer appointment routes.
        $this->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/v1/customer/appointments'
            )
            ->assertForbidden();
    }
}
