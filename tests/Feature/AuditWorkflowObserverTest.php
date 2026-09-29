<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\AuditEvent;
use App\Enums\RescheduleRequestStatus;
use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\SmsNotificationType;
use App\Jobs\SendSmsNotification;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Promo;
use App\Models\RescheduleRequest;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopSchedule;
use App\Models\SmsNotification;
use App\Models\User;
use App\Observers\Audit\AppointmentAuditObserver;
use App\Observers\Audit\FeedbackAuditObserver;
use App\Observers\Audit\PromoAuditObserver;
use App\Observers\Audit\RescheduleRequestAuditObserver;
use App\Observers\Audit\ServiceAuditObserver;
use App\Observers\Audit\ShopAuditObserver;
use App\Observers\Audit\ShopScheduleAuditObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditWorkflowObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_shop_approval_is_audited(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::withoutEvents(
                fn (): Shop =>
                    Shop::factory()
                        ->create()
            );

        $this->actingAs(
            $admin,
            'sanctum'
        );

        $shop->approval_status =
            ShopApprovalStatus::Approved;

        $shop->saveQuietly();

        app(
            ShopAuditObserver::class
        )->updated(
            $shop
        );

        $log =
            AuditLog::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            AuditEvent::ShopApproved,
            $log->event
        );

        $this->assertSame(
            $admin->id,
            $log->actor_id
        );

        $this->assertSame(
            $shop->id,
            $log->auditable_id
        );
    }

    public function test_shop_availability_change_is_audited(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        /*
        |--------------------------------------------------------------------------
        | Deterministic Initial Availability
        |--------------------------------------------------------------------------
        |
        | The shop MUST start as Open so changing it to Closed below is an
        | actual model change.
        |
        | Without this explicit value, the factory could already generate
        | Closed, causing wasChanged('availability_status') to return false.
        |
        */

        $shop =
            Shop::withoutEvents(
                fn (): Shop =>
                    Shop::factory()
                        ->approved()
                        ->create([
                            'owner_id' =>
                                $owner->id,

                            'availability_status' =>
                                ShopAvailabilityStatus::Open,
                        ])
            );

        $this->assertSame(
            ShopAvailabilityStatus::Open,
            $shop->availability_status
        );

        $this->actingAs(
            $owner,
            'sanctum'
        );

        $shop->availability_status =
            ShopAvailabilityStatus::Closed;

        $shop->saveQuietly();

        /*
        |--------------------------------------------------------------------------
        | Verify an Actual Change Occurred
        |--------------------------------------------------------------------------
        */

        $this->assertTrue(
            $shop->wasChanged(
                'availability_status'
            )
        );

        app(
            ShopAuditObserver::class
        )->updated(
            $shop
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $owner->id,

                'event' =>
                    AuditEvent::ShopAvailabilityUpdated
                        ->value,

                'auditable_id' =>
                    $shop->id,
            ]
        );
    }

    public function test_service_creation_is_audited(): void
    {
        [$owner, $shop] =
            $this->ownerAndShop();

        $service =
            Service::withoutEvents(
                fn (): Service =>
                    Service::factory()
                        ->create([
                            'shop_id' =>
                                $shop->id,
                        ])
            );

        $this->actingAs(
            $owner,
            'sanctum'
        );

        app(
            ServiceAuditObserver::class
        )->created(
            $service
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $owner->id,

                'event' =>
                    AuditEvent::ServiceCreated
                        ->value,

                'auditable_id' =>
                    $service->id,
            ]
        );
    }

    public function test_service_update_is_audited(): void
    {
        [$owner, $shop] =
            $this->ownerAndShop();

        $service =
            Service::withoutEvents(
                fn (): Service =>
                    Service::factory()
                        ->create([
                            'shop_id' =>
                                $shop->id,

                            'name' =>
                                'Basic Haircut',
                        ])
            );

        $this->actingAs(
            $owner,
            'sanctum'
        );

        $service->name =
            'Premium Haircut';

        $service->saveQuietly();

        app(
            ServiceAuditObserver::class
        )->updated(
            $service
        );

        $log =
            AuditLog::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            AuditEvent::ServiceUpdated,
            $log->event
        );

        $this->assertContains(
            'name',
            $log->metadata[
                'changed_fields'
            ]
        );
    }

    public function test_schedule_change_is_audited(): void
    {
        [$owner, $shop] =
            $this->ownerAndShop();

        $schedule =
            ShopSchedule::withoutEvents(
                fn (): ShopSchedule =>
                    ShopSchedule::factory()
                        ->create([
                            'shop_id' =>
                                $shop->id,
                        ])
            );

        $this->actingAs(
            $owner,
            'sanctum'
        );

        $schedule->is_open =
            ! $schedule->is_open;

        $schedule->saveQuietly();

        app(
            ShopScheduleAuditObserver::class
        )->updated(
            $schedule
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $owner->id,

                'event' =>
                    AuditEvent::ShopScheduleUpdated
                        ->value,

                'auditable_id' =>
                    $schedule->id,
            ]
        );
    }

    public function test_promo_creation_is_audited(): void
    {
        [$owner, $shop] =
            $this->ownerAndShop();

        $promo =
            Promo::withoutEvents(
                fn (): Promo =>
                    Promo::factory()
                        ->create([
                            'shop_id' =>
                                $shop->id,
                        ])
            );

        $this->actingAs(
            $owner,
            'sanctum'
        );

        app(
            PromoAuditObserver::class
        )->created(
            $promo
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $owner->id,

                'event' =>
                    AuditEvent::PromoCreated
                        ->value,

                'auditable_id' =>
                    $promo->id,
            ]
        );
    }

    public function test_customer_booking_is_audited(): void
    {
        [$customer, $appointment] =
            $this->appointment();

        $this->actingAs(
            $customer,
            'sanctum'
        );

        app(
            AppointmentAuditObserver::class
        )->created(
            $appointment
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $customer->id,

                'event' =>
                    AuditEvent::AppointmentBooked
                        ->value,

                'auditable_id' =>
                    $appointment->id,
            ]
        );
    }

    public function test_appointment_confirmation_is_audited(): void
    {
        [, $appointment, $owner] =
            $this->appointment();

        $this->actingAs(
            $owner,
            'sanctum'
        );

        $appointment->status =
            AppointmentStatus::Confirmed;

        $appointment->saveQuietly();

        app(
            AppointmentAuditObserver::class
        )->updated(
            $appointment
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $owner->id,

                'event' =>
                    AuditEvent::AppointmentConfirmed
                        ->value,

                'auditable_id' =>
                    $appointment->id,
            ]
        );
    }

    public function test_reschedule_request_is_audited(): void
    {
        [$customer, $appointment] =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $request =
            RescheduleRequest::withoutEvents(
                fn (): RescheduleRequest =>
                    RescheduleRequest::factory()
                        ->create([
                            'appointment_id' =>
                                $appointment->id,

                            'status' =>
                                RescheduleRequestStatus::Pending,
                        ])
            );

        $this->actingAs(
            $customer,
            'sanctum'
        );

        app(
            RescheduleRequestAuditObserver::class
        )->created(
            $request
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $customer->id,

                'event' =>
                    AuditEvent::RescheduleRequested
                        ->value,

                'auditable_id' =>
                    $request->id,
            ]
        );
    }

    public function test_reschedule_approval_is_audited(): void
    {
        [, $appointment, $owner] =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $request =
            RescheduleRequest::withoutEvents(
                fn (): RescheduleRequest =>
                    RescheduleRequest::factory()
                        ->create([
                            'appointment_id' =>
                                $appointment->id,

                            'status' =>
                                RescheduleRequestStatus::Pending,
                        ])
            );

        $this->actingAs(
            $owner,
            'sanctum'
        );

        $request->status =
            RescheduleRequestStatus::Approved;

        $request->saveQuietly();

        app(
            RescheduleRequestAuditObserver::class
        )->updated(
            $request
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $owner->id,

                'event' =>
                    AuditEvent::RescheduleApproved
                        ->value,

                'auditable_id' =>
                    $request->id,
            ]
        );
    }

    public function test_feedback_submission_is_audited(): void
    {
        [$customer, $appointment] =
            $this->appointment(
                AppointmentStatus::Completed
            );

        $feedback =
            Feedback::withoutEvents(
                function () use (
                    $customer,
                    $appointment
                ): Feedback {
                    $feedback =
                        new Feedback([
                            'rating' =>
                                5,

                            'comment' =>
                                'Excellent service.',
                        ]);

                    $feedback->appointment_id =
                        $appointment->id;

                    $feedback->customer_id =
                        $customer->id;

                    $feedback->shop_id =
                        $appointment->shop_id;

                    $feedback->save();

                    return $feedback;
                }
            );

        $this->actingAs(
            $customer,
            'sanctum'
        );

        app(
            FeedbackAuditObserver::class
        )->created(
            $feedback
        );

        $log =
            AuditLog::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            AuditEvent::FeedbackSubmitted,
            $log->event
        );

        $this->assertSame(
            5,
            $log->metadata[
                'rating'
            ]
        );

        /*
         * Review body itself is not copied into audit metadata.
         */
        $this->assertArrayNotHasKey(
            'comment',
            $log->metadata
        );
    }

    public function test_unauthenticated_model_event_does_not_create_user_audit_entry(): void
    {
        $shop =
            Shop::withoutEvents(
                fn (): Shop =>
                    Shop::factory()
                        ->approved()
                        ->create()
            );

        $service =
            Service::withoutEvents(
                fn (): Service =>
                    Service::factory()
                        ->create([
                            'shop_id' =>
                                $shop->id,
                        ])
            );

        app(
            ServiceAuditObserver::class
        )->created(
            $service
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_controlled_sms_retry_creates_system_audit_entry(): void
    {
        Queue::fake();

        $notification =
            SmsNotification::factory()
                ->failed()
                ->create([
                    'type' =>
                        SmsNotificationType::BookingReceived,
                ]);

        $this->artisan(
            'stylehub:sms:retry',
            [
                'notification' =>
                    $notification->id,
            ]
        )->assertSuccessful();

        $log =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::SmsRetryQueued
                        ->value
                )
                ->firstOrFail();

        $this->assertNull(
            $log->actor_id
        );

        $this->assertSame(
            $notification->id,
            $log->auditable_id
        );

        $this->assertSame(
            'cli',
            $log->metadata[
                'source'
            ]
        );

        Queue::assertPushed(
            SendSmsNotification::class
        );
    }

    /**
     * @return array{0: User, 1: Shop}
     */
    private function ownerAndShop(): array
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::withoutEvents(
                fn (): Shop =>
                    Shop::factory()
                        ->approved()
                        ->create([
                            'owner_id' =>
                                $owner->id,
                        ])
            );

        return [
            $owner,
            $shop,
        ];
    }

    /**
     * @return array{0: User, 1: Appointment, 2: User}
     */
    private function appointment(
        AppointmentStatus $status =
            AppointmentStatus::Pending
    ): array {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::withoutEvents(
                fn (): Shop =>
                    Shop::factory()
                        ->approved()
                        ->create([
                            'owner_id' =>
                                $owner->id,
                        ])
            );

        $service =
            Service::withoutEvents(
                fn (): Service =>
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
                        ])
            );

        $appointment =
            Appointment::withoutEvents(
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
                                now()
                                    ->addDay()
                                    ->toDateString(),

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

        return [
            $customer,
            $appointment,
            $owner,
        ];
    }
}