<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\InAppNotificationType;
use App\Enums\RescheduleRequestStatus;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\InAppNotification;
use App\Models\RescheduleRequest;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use App\Observers\AppointmentObserver;
use App\Observers\FeedbackObserver;
use App\Observers\RescheduleRequestObserver;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

class InAppNotificationWorkflowObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Existing SMS behavior still executes from the same observers.
         *
         * We fake the queue so these tests remain completely local and
         * never attempt a real Semaphore request.
         */
        Queue::fake();
    }

    public function test_models_are_registered_with_notification_observers(): void
    {
        foreach (
            [
                Appointment::class,
                RescheduleRequest::class,
                Feedback::class,
            ] as $modelClass
        ) {
            $attributes =
                (
                    new ReflectionClass(
                        $modelClass
                    )
                )->getAttributes(
                    ObservedBy::class
                );

            $this->assertNotEmpty(
                $attributes
            );
        }
    }

    public function test_new_booking_notifies_shop_owner_not_customer(): void
    {
        $appointment =
            $this->appointment();

        app(
            AppointmentObserver::class
        )->created(
            $appointment
        );

        $notification =
            InAppNotification::query()
                ->firstOrFail();

        $this->assertSame(
            $appointment->shop->owner_id,
            $notification->user_id
        );

        $this->assertNotSame(
            $appointment->customer_id,
            $notification->user_id
        );

        $this->assertSame(
            InAppNotificationType::BookingReceived,
            $notification->type
        );

        $this->assertSame(
            $appointment->id,
            $notification->appointment_id
        );
    }

    public function test_confirmed_appointment_notifies_customer(): void
    {
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

        $this->assertNotification(
            userId:
                $appointment->customer_id,

            type:
                InAppNotificationType::AppointmentConfirmed
        );
    }

    public function test_declined_appointment_notifies_customer(): void
    {
        $appointment =
            $this->appointment();

        $appointment->status =
            AppointmentStatus::Declined;

        $appointment->cancelled_by =
            $appointment->shop->owner_id;

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $this->assertNotification(
            userId:
                $appointment->customer_id,

            type:
                InAppNotificationType::AppointmentDeclined
        );
    }

    public function test_customer_cancellation_notifies_shop_owner_instead_of_customer(): void
    {
        $appointment =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $appointment->status =
            AppointmentStatus::Cancelled;

        $appointment->cancelled_by =
            $appointment->customer_id;

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $this->assertNotification(
            userId:
                $appointment->shop->owner_id,

            type:
                InAppNotificationType::AppointmentCancelled
        );

        $this->assertDatabaseMissing(
            'in_app_notifications',
            [
                'user_id' =>
                    $appointment->customer_id,

                'type' =>
                    InAppNotificationType::AppointmentCancelled
                        ->value,
            ]
        );
    }

    public function test_shop_cancellation_notifies_customer_instead_of_owner(): void
    {
        $appointment =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $appointment->status =
            AppointmentStatus::Cancelled;

        $appointment->cancelled_by =
            $appointment->shop->owner_id;

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $this->assertNotification(
            userId:
                $appointment->customer_id,

            type:
                InAppNotificationType::AppointmentCancelled
        );

        $this->assertDatabaseMissing(
            'in_app_notifications',
            [
                'user_id' =>
                    $appointment->shop->owner_id,

                'type' =>
                    InAppNotificationType::AppointmentCancelled
                        ->value,
            ]
        );
    }

    public function test_completed_appointment_notifies_customer_without_sms_requirement(): void
    {
        $appointment =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $appointment->status =
            AppointmentStatus::Completed;

        $appointment->completed_at =
            now();

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $notification =
            $this->assertNotification(
                userId:
                    $appointment->customer_id,

                type:
                    InAppNotificationType::AppointmentCompleted
            );

        $this->assertStringContainsString(
            'leave a review',
            $notification->message
        );
    }

    public function test_new_reschedule_request_notifies_shop_owner(): void
    {
        $appointment =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        app(
            RescheduleRequestObserver::class
        )->created(
            $request
        );

        $notification =
            $this->assertNotification(
                userId:
                    $appointment->shop->owner_id,

                type:
                    InAppNotificationType::RescheduleRequested
            );

        $this->assertSame(
            $request->id,
            $notification->reschedule_request_id
        );
    }

    public function test_approved_reschedule_notifies_customer(): void
    {
        $appointment =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $request =
            $this->rescheduleRequest(
                $appointment
            );

        $request->status =
            RescheduleRequestStatus::Approved;

        $request->saveQuietly();

        app(
            RescheduleRequestObserver::class
        )->updated(
            $request
        );

        $this->assertNotification(
            userId:
                $appointment->customer_id,

            type:
                InAppNotificationType::RescheduleApproved
        );
    }

    public function test_rejected_reschedule_notifies_customer(): void
    {
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
            'Requested time is unavailable.';

        $request->saveQuietly();

        app(
            RescheduleRequestObserver::class
        )->updated(
            $request
        );

        $this->assertNotification(
            userId:
                $appointment->customer_id,

            type:
                InAppNotificationType::RescheduleRejected
        );
    }

    public function test_new_feedback_notifies_shop_owner(): void
    {
        $appointment =
            $this->appointment(
                AppointmentStatus::Completed
            );

        $feedback =
            Feedback::withoutEvents(
                fn (): Feedback =>
                    Feedback::factory()
                        ->rating(
                            5
                        )
                        ->create([
                            'appointment_id' =>
                                $appointment->id,

                            'customer_id' =>
                                $appointment->customer_id,

                            'shop_id' =>
                                $appointment->shop_id,
                        ])
            );

        app(
            FeedbackObserver::class
        )->created(
            $feedback
        );

        $notification =
            $this->assertNotification(
                userId:
                    $appointment->shop->owner_id,

                type:
                    InAppNotificationType::ReviewReceived
            );

        $this->assertSame(
            $feedback->id,
            $notification->feedback_id
        );

        $this->assertStringContainsString(
            '5-star review',
            $notification->message
        );
    }

    public function test_non_status_appointment_update_does_not_create_in_app_notification(): void
    {
        $appointment =
            $this->appointment();

        $appointment->notes =
            'Updated note only.';

        $appointment->saveQuietly();

        app(
            AppointmentObserver::class
        )->updated(
            $appointment
        );

        $this->assertDatabaseCount(
            'in_app_notifications',
            0
        );
    }

    public function test_replaying_same_business_event_is_deduplicated(): void
    {
        $appointment =
            $this->appointment();

        $service =
            app(
                InAppNotificationService::class
            );

        $first =
            $service->bookingReceived(
                $appointment
            );

        $second =
            $service->bookingReceived(
                $appointment
            );

        $this->assertNotNull(
            $first
        );

        $this->assertNotNull(
            $second
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'in_app_notifications',
            1
        );
    }

    private function appointment(
        AppointmentStatus $status =
            AppointmentStatus::Pending
    ): Appointment {
        $customer =
            User::factory()
                ->customer()
                ->create();

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
                            '2026-09-15',

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
        )->load([
            'customer',
            'shop.owner',
        ]);
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
                            '2026-09-16',

                        'requested_starts_at' =>
                            '13:00:00',

                        'requested_ends_at' =>
                            '14:00:00',

                        'status' =>
                            RescheduleRequestStatus::Pending,
                    ])
        );
    }

    private function assertNotification(
        int $userId,
        InAppNotificationType $type
    ): InAppNotification {
        $notification =
            InAppNotification::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'type',
                    $type->value
                )
                ->first();

        $this->assertNotNull(
            $notification,
            sprintf(
                'Expected %s notification for user %d.',
                $type->value,
                $userId
            )
        );

        return $notification;
    }
}