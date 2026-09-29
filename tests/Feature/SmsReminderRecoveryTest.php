<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Jobs\SendSmsNotification;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\SmsNotification;
use App\Models\User;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Support\AppointmentReminderKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsReminderRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' =>
                'Asia/Manila',

            'sms.enabled' =>
                false,

            'sms.reminders.lead_minutes' =>
                1440,

            'sms.reminders.minimum_lead_minutes' =>
                60,

            'sms.recovery.pending_after_minutes' =>
                5,

            'sms.recovery.processing_after_minutes' =>
                10,
        ]);

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-21 08:00:00',
                'Asia/Manila'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_confirmed_appointment_inside_window_gets_one_reminder(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment(
                status:
                    AppointmentStatus::Confirmed,

                date:
                    '2026-08-22',

                startsAt:
                    '07:30:00',
            );

        $this->artisan(
            'stylehub:sms:queue-reminders'
        )->assertSuccessful();

        $notification =
            SmsNotification::query()
                ->where(
                    'type',
                    SmsNotificationType::AppointmentReminder
                        ->value
                )
                ->firstOrFail();

        $this->assertSame(
            $appointment->id,
            $notification->appointment_id
        );

        $this->assertSame(
            SmsNotificationStatus::Pending,
            $notification->status
        );

        $this->assertSame(
            AppointmentReminderKey::for(
                $appointment
            ),
            $notification->dedupe_key
        );

        Queue::assertPushed(
            SendSmsNotification::class,
            1
        );
    }

    public function test_running_reminder_scheduler_twice_does_not_duplicate_same_schedule(): void
    {
        Queue::fake();

        $this->appointment(
            status:
                AppointmentStatus::Confirmed,

            date:
                '2026-08-22',

            startsAt:
                '07:30:00',
        );

        $this->artisan(
            'stylehub:sms:queue-reminders'
        )->assertSuccessful();

        $this->artisan(
            'stylehub:sms:queue-reminders'
        )->assertSuccessful();

        $this->assertDatabaseCount(
            'sms_notifications',
            1
        );

        Queue::assertPushed(
            SendSmsNotification::class,
            1
        );
    }

    public function test_appointment_more_than_twenty_four_hours_away_is_not_reminded(): void
    {
        Queue::fake();

        $this->appointment(
            status:
                AppointmentStatus::Confirmed,

            date:
                '2026-08-22',

            startsAt:
                '09:30:00',
        );

        $this->artisan(
            'stylehub:sms:queue-reminders'
        )->assertSuccessful();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );

        Queue::assertNothingPushed();
    }

    public function test_appointment_too_close_to_start_time_is_not_reminded(): void
    {
        Queue::fake();

        $this->appointment(
            status:
                AppointmentStatus::Confirmed,

            date:
                '2026-08-21',

            startsAt:
                '08:30:00',
        );

        $this->artisan(
            'stylehub:sms:queue-reminders'
        )->assertSuccessful();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );

        Queue::assertNothingPushed();
    }

    public function test_pending_appointment_does_not_receive_scheduled_reminder(): void
    {
        Queue::fake();

        $this->appointment(
            status:
                AppointmentStatus::Pending,

            date:
                '2026-08-22',

            startsAt:
                '07:30:00',
        );

        $this->artisan(
            'stylehub:sms:queue-reminders'
        )->assertSuccessful();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );
    }

    public function test_rescheduled_appointment_can_receive_new_reminder_for_new_schedule(): void
    {
        Queue::fake();

        $appointment =
            $this->appointment(
                status:
                    AppointmentStatus::Confirmed,

                date:
                    '2026-08-22',

                startsAt:
                    '07:30:00',
            );

        $this->artisan(
            'stylehub:sms:queue-reminders'
        )->assertSuccessful();

        $firstKey =
            AppointmentReminderKey::for(
                $appointment
            );

        $appointment->starts_at =
            '07:45:00';

        $appointment->ends_at =
            '08:45:00';

        $appointment->saveQuietly();

        $appointment->refresh();

        $secondKey =
            AppointmentReminderKey::for(
                $appointment
            );

        $this->assertNotSame(
            $firstKey,
            $secondKey
        );

        $this->artisan(
            'stylehub:sms:queue-reminders'
        )->assertSuccessful();

        $this->assertDatabaseCount(
            'sms_notifications',
            2
        );
    }

    public function test_old_reminder_is_skipped_if_schedule_changed_before_job_runs(): void
    {
        config([
            'sms.enabled' =>
                true,
        ]);

        Http::fake();

        $appointment =
            $this->appointment(
                status:
                    AppointmentStatus::Confirmed,

                date:
                    '2026-08-22',

                startsAt:
                    '07:30:00',
            );

        $oldKey =
            AppointmentReminderKey::for(
                $appointment
            );

        $notification =
            SmsNotification::factory()
                ->create([
                    'user_id' =>
                        $appointment->customer_id,

                    'appointment_id' =>
                        $appointment->id,

                    'type' =>
                        SmsNotificationType::AppointmentReminder,

                    'status' =>
                        SmsNotificationStatus::Pending,

                    'dedupe_key' =>
                        $oldKey,
                ]);

        $appointment->starts_at =
            '07:45:00';

        $appointment->ends_at =
            '08:45:00';

        $appointment->saveQuietly();

        (
            new SendSmsNotification(
                $notification->id
            )
        )->handle(
            new SemaphoreSmsGateway()
        );

        $updated =
            $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Skipped,
            $updated->status
        );

        $this->assertSame(
            'Appointment schedule changed before the reminder was sent.',
            $updated->last_error
        );

        Http::assertNothingSent();
    }

    public function test_stale_pending_notification_is_safely_redispatched(): void
    {
        Queue::fake();

        $notification =
            SmsNotification::factory()
                ->create([
                    'status' =>
                        SmsNotificationStatus::Pending,
                ]);

        $notification->forceFill([
            'created_at' =>
                now()
                    ->subMinutes(10),

            'updated_at' =>
                now()
                    ->subMinutes(10),
        ])->saveQuietly();

        $this->artisan(
            'stylehub:sms:recover'
        )->assertSuccessful();

        Queue::assertPushed(
            SendSmsNotification::class,
            fn (
                SendSmsNotification $job
            ): bool =>
                $job->smsNotificationId
                === $notification->id
        );

        $this->assertSame(
            SmsNotificationStatus::Pending,
            $notification
                ->fresh()
                ->status
        );
    }

    public function test_stale_processing_notification_becomes_uncertain_failure_without_resend(): void
    {
        Queue::fake();

        $notification =
            SmsNotification::factory()
                ->create([
                    'status' =>
                        SmsNotificationStatus::Processing,

                    'attempts' =>
                        1,

                    'last_attempt_at' =>
                        now()
                            ->subMinutes(20),
                ]);

        $this->artisan(
            'stylehub:sms:recover'
        )->assertSuccessful();

        $updated =
            $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Failed,
            $updated->status
        );

        $this->assertSame(
            SmsFailureKind::Uncertain,
            $updated->failure_kind
        );

        Queue::assertNothingPushed();
    }

    public function test_definite_failure_can_be_manually_retried(): void
    {
        Queue::fake();

        $notification =
            SmsNotification::factory()
                ->failed()
                ->create();

        $this->artisan(
            'stylehub:sms:retry',
            [
                'notification' =>
                    $notification->id,
            ]
        )->assertSuccessful();

        $updated =
            $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Pending,
            $updated->status
        );

        $this->assertNull(
            $updated->failure_kind
        );

        $this->assertNull(
            $updated->last_error
        );

        /*
         * Historical attempt counter is retained.
         */
        $this->assertSame(
            1,
            $updated->attempts
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

    public function test_uncertain_failure_cannot_be_manually_retried(): void
    {
        Queue::fake();

        $notification =
            SmsNotification::factory()
                ->uncertainFailure()
                ->create();

        $this->artisan(
            'stylehub:sms:retry',
            [
                'notification' =>
                    $notification->id,
            ]
        )->assertExitCode(
            1
        );

        $updated =
            $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Failed,
            $updated->status
        );

        $this->assertSame(
            SmsFailureKind::Uncertain,
            $updated->failure_kind
        );

        Queue::assertNothingPushed();
    }

    private function appointment(
        AppointmentStatus $status,
        string $date,
        string $startsAt
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

        $end =
            CarbonImmutable::parse(
                $date
                .' '
                .$startsAt,
                'Asia/Manila'
            )
                ->addMinutes(60)
                ->format(
                    'H:i:s'
                );

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
                            $date,

                        'starts_at' =>
                            $startsAt,

                        'ends_at' =>
                            $end,

                        'service_name' =>
                            $service->name,

                        'price_snapshot' =>
                            $service->price,

                        'duration_minutes_snapshot' =>
                            60,

                        'status' =>
                            $status,
                    ])
        );
    }
}