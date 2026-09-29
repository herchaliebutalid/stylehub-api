<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\RescheduleRequestStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Jobs\SendSmsNotification;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopSchedule;
use App\Models\SmsNotification;
use App\Models\User;
use App\Observers\AppointmentObserver;
use App\Observers\RescheduleRequestObserver;
use App\Services\Sms\AppointmentSmsNotifier;
use App\Services\Sms\SmsNotificationService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class SmsWorkflowObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_are_registered_with_sms_observers(): void
    {
        $appointmentAttributes =
            (
                new ReflectionClass(
                    Appointment::class
                )
            )->getAttributes(
                ObservedBy::class
            );

        $rescheduleAttributes =
            (
                new ReflectionClass(
                    RescheduleRequest::class
                )
            )->getAttributes(
                ObservedBy::class
            );

        $this->assertNotEmpty(
            $appointmentAttributes
        );

        $this->assertNotEmpty(
            $rescheduleAttributes
        );
    }

    public function test_new_pending_appointment_queues_booking_received_sms(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment();

        $observer =
            app(
                AppointmentObserver::class
            );

        /*
         * RefreshDatabase keeps tests inside a transaction, while the
         * production observer is configured to run after commit.
         *
         * We invoke the observer directly here to test its workflow logic
         * without defeating Laravel's transaction-safe production behavior.
         */
        $observer->created(
            $appointment
        );

        $notification =
            SmsNotification::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            SmsNotificationType::BookingReceived,
            $notification->type
        );

        $this->assertSame(
            SmsNotificationStatus::Pending,
            $notification->status
        );

        $this->assertSame(
            $appointment->id,
            $notification->appointment_id
        );

        $this->assertStringContainsString(
            'Status: Pending',
            $notification->message
        );

        Queue::assertPushed(
            SendSmsNotification::class,
            fn (
                SendSmsNotification $job
            ): bool =>
                $job->smsNotificationId
                === $notification->id
        );
    }

    public function test_confirming_appointment_queues_confirmation_sms(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment();

        $appointment->status =
            AppointmentStatus::Confirmed;

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $notification =
            SmsNotification::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            SmsNotificationType::AppointmentConfirmed,
            $notification->type
        );

        $this->assertStringContainsString(
            'is confirmed',
            $notification->message
        );
    }

    public function test_declining_appointment_queues_declined_sms(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment();

        $appointment->status =
            AppointmentStatus::Declined;

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $notification =
            SmsNotification::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            SmsNotificationType::AppointmentDeclined,
            $notification->type
        );
    }

    public function test_cancelling_appointment_queues_cancellation_sms(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $appointment->status =
            AppointmentStatus::Cancelled;

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $notification =
            SmsNotification::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            SmsNotificationType::AppointmentCancelled,
            $notification->type
        );

        $this->assertStringContainsString(
            'was cancelled',
            $notification->message
        );
    }

    public function test_completing_appointment_does_not_queue_sms(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $appointment->status =
            AppointmentStatus::Completed;

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );

        Queue::assertNothingPushed();
    }

    public function test_non_status_appointment_update_does_not_queue_sms(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment();

        $appointment->notes =
            'Updated customer note.';

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );

        Queue::assertNothingPushed();
    }

    public function test_approved_reschedule_queues_sms_with_new_schedule(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        /*
         * Simulate the real approval workflow:
         * appointment moves first, request becomes approved.
         */
        $appointment->appointment_date =
            '2026-08-24';

        $appointment->starts_at =
            '13:00:00';

        $appointment->ends_at =
            '14:00:00';

        $appointment->saveQuietly();

        $request->status =
            RescheduleRequestStatus::Approved;

        $request->reviewed_at =
            now();

        $request->saveQuietly();

        app(
            RescheduleRequestObserver::class
        )->updated(
            $request
        );

        $notification =
            SmsNotification::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            SmsNotificationType::RescheduleApproved,
            $notification->type
        );

        $this->assertStringContainsString(
            'reschedule request was approved',
            $notification->message
        );

        $this->assertStringContainsString(
            '1:00 PM',
            $notification->message
        );
    }

    public function test_rejected_reschedule_queues_rejection_sms(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        $request->status =
            RescheduleRequestStatus::Rejected;

        $request->rejection_reason =
            'Requested slot is unavailable.';

        $request->reviewed_at =
            now();

        $request->saveQuietly();

        app(
            RescheduleRequestObserver::class
        )->updated(
            $request
        );

        $notification =
            SmsNotification::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            SmsNotificationType::RescheduleRejected,
            $notification->type
        );

        $this->assertStringContainsString(
            'was not approved',
            $notification->message
        );

        $this->assertStringContainsString(
            'current appointment remains unchanged',
            $notification->message
        );
    }

    public function test_sms_infrastructure_exception_is_isolated_from_business_workflow(): void
    {
        $service =
            Mockery::mock(
                SmsNotificationService::class
            );

        $service
            ->shouldReceive(
                'queue'
            )
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Simulated SMS infrastructure failure.'
                )
            );

        $notifier =
            new AppointmentSmsNotifier(
                $service
            );

        $appointment =
            $this->appointment();

        /*
         * AppointmentSmsNotifier must swallow/report the SMS failure.
         * If it escapes, this test fails.
         */
        $notifier->bookingReceived(
            $appointment
        );

        $this->assertTrue(
            $appointment->exists
        );
    }

    private function appointment(
        AppointmentStatus $status =
            AppointmentStatus::Pending
    ): Appointment {
        $customer =
            User::factory()
                ->customer()
                ->create([
                    'phone_number' =>
                        '09171234567',
                ]);

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

        return Appointment::withoutEvents(
            fn (): Appointment =>
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

                        'completed_at' =>
                            $status
                            === AppointmentStatus::Completed
                                ? now()
                                : null,
                    ])
        );
    }

    private function rescheduleRequest(
        Appointment $appointment
    ): RescheduleRequest {
        return RescheduleRequest::withoutEvents(
            fn (): RescheduleRequest =>
                RescheduleRequest::factory()
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
                            RescheduleRequestStatus::Pending,
                    ])
        );
    }
}