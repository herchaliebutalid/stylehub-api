<?php

namespace Tests\Feature;

use App\Enums\InAppNotificationType;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\InAppNotification;
use App\Models\Promo;
use App\Models\RescheduleRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InAppNotificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_app_notification_table_has_required_foundation_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'in_app_notifications',
                [
                    'id',
                    'user_id',
                    'type',
                    'title',
                    'message',
                    'appointment_id',
                    'reschedule_request_id',
                    'feedback_id',
                    'promo_id',
                    'dedupe_key',
                    'read_at',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_factory_creates_unread_typed_notification(): void
    {
        $notification =
            InAppNotification::factory()
                ->create();

        $this->assertSame(
            InAppNotificationType::BookingReceived,
            $notification->type
        );

        $this->assertTrue(
            $notification->isUnread()
        );

        $this->assertFalse(
            $notification->isRead()
        );

        $this->assertNull(
            $notification->read_at
        );
    }

    public function test_notification_can_reference_user_and_supported_source_records(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $appointment =
            Appointment::factory()
                ->completed()
                ->create([
                    'customer_id' =>
                        $customer->id,
                ]);

        $rescheduleRequest =
            RescheduleRequest::factory()
                ->create([
                    'appointment_id' =>
                        $appointment->id,
                ]);

        $feedback =
            Feedback::factory()
                ->create([
                    'appointment_id' =>
                        $appointment->id,
                ]);

        $promo =
            Promo::factory()
                ->create();

        $notification =
            InAppNotification::factory()
                ->create([
                    'user_id' =>
                        $customer->id,

                    'appointment_id' =>
                        $appointment->id,

                    'reschedule_request_id' =>
                        $rescheduleRequest->id,

                    'feedback_id' =>
                        $feedback->id,

                    'promo_id' =>
                        $promo->id,
                ]);

        $this->assertSame(
            $customer->id,
            $notification->user->id
        );

        $this->assertSame(
            $appointment->id,
            $notification->appointment->id
        );

        $this->assertSame(
            $rescheduleRequest->id,
            $notification->rescheduleRequest->id
        );

        $this->assertSame(
            $feedback->id,
            $notification->feedback->id
        );

        $this->assertSame(
            $promo->id,
            $notification->promo->id
        );

        $this->assertTrue(
            $customer
                ->inAppNotifications()
                ->whereKey(
                    $notification->id
                )
                ->exists()
        );
    }

    public function test_notification_ownership_read_state_and_dedupe_key_cannot_be_mass_assigned(): void
    {
        $notification =
            new InAppNotification();

        $notification->fill([
            'user_id' =>
                999,

            'appointment_id' =>
                999,

            'reschedule_request_id' =>
                999,

            'feedback_id' =>
                999,

            'promo_id' =>
                999,

            'type' =>
                InAppNotificationType::AppointmentConfirmed
                    ->value,

            'title' =>
                'Safe title',

            'message' =>
                'Safe notification message.',

            'dedupe_key' =>
                'forged-dedupe-key',

            'read_at' =>
                now(),
        ]);

        $this->assertNull(
            $notification->user_id
        );

        $this->assertNull(
            $notification->appointment_id
        );

        $this->assertNull(
            $notification->reschedule_request_id
        );

        $this->assertNull(
            $notification->feedback_id
        );

        $this->assertNull(
            $notification->promo_id
        );

        $this->assertNull(
            $notification->dedupe_key
        );

        $this->assertNull(
            $notification->read_at
        );

        $this->assertSame(
            'Safe title',
            $notification->title
        );

        $this->assertSame(
            'Safe notification message.',
            $notification->message
        );
    }

    public function test_unread_scope_returns_only_unread_notifications(): void
    {
        $user =
            User::factory()
                ->customer()
                ->create();

        $unread =
            InAppNotification::factory()
                ->create([
                    'user_id' =>
                        $user->id,
                ]);

        InAppNotification::factory()
            ->read()
            ->create([
                'user_id' =>
                    $user->id,
            ]);

        $ids =
            $user
                ->inAppNotifications()
                ->unread()
                ->pluck(
                    'id'
                )
                ->all();

        $this->assertSame(
            [
                $unread->id,
            ],
            $ids
        );
    }

    public function test_dedupe_key_is_unique_when_present(): void
    {
        InAppNotification::factory()
            ->create([
                'dedupe_key' =>
                    'appointment:123:confirmed:user:456',
            ]);

        $this->expectException(
            QueryException::class
        );

        InAppNotification::factory()
            ->create([
                'dedupe_key' =>
                    'appointment:123:confirmed:user:456',
            ]);
    }
}