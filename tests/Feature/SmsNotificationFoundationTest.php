<?php

namespace Tests\Feature;

use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Exceptions\Sms\SmsGatewayException;
use App\Jobs\SendSmsNotification;
use App\Models\Appointment;
use App\Models\SmsNotification;
use App\Models\User;
use App\Services\Sms\SemaphoreSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsNotificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_notification_factory_creates_pending_record(): void
    {
        $notification =
            SmsNotification::factory()
                ->create();

        $this->assertSame(
            SmsNotificationType::BookingReceived,
            $notification->type
        );

        $this->assertSame(
            SmsNotificationStatus::Pending,
            $notification->status
        );

        $this->assertSame(
            'semaphore',
            $notification->provider
        );

        $this->assertSame(
            0,
            $notification->attempts
        );
    }

    public function test_sensitive_sms_delivery_fields_cannot_be_mass_assigned(): void
    {
        $notification =
            new SmsNotification();

        $notification->fill([
            'user_id' =>
                999,

            'appointment_id' =>
                999,

            'type' =>
                SmsNotificationType::BookingReceived
                    ->value,

            'recipient' =>
                '09171234567',

            'message' =>
                'Safe StyleHub notification.',

            'status' =>
                SmsNotificationStatus::Accepted
                    ->value,

            'provider_message_id' =>
                'malicious-id',

            'provider_status' =>
                'Sent',
        ]);

        $this->assertNull(
            $notification->user_id
        );

        $this->assertNull(
            $notification->appointment_id
        );

        $this->assertNull(
            $notification->provider_message_id
        );

        $this->assertNull(
            $notification->provider_status
        );

        $this->assertSame(
            '09171234567',
            $notification->recipient
        );

        $this->assertSame(
            'Safe StyleHub notification.',
            $notification->message
        );
    }

    public function test_sms_notification_can_belong_to_user_and_appointment(): void
    {
        $user =
            User::factory()
                ->customer()
                ->create();

        $appointment =
            Appointment::factory()
                ->create([
                    'customer_id' =>
                        $user->id,
                ]);

        $notification =
            SmsNotification::factory()
                ->create([
                    'user_id' =>
                        $user->id,

                    'appointment_id' =>
                        $appointment->id,
                ]);

        $this->assertSame(
            $user->id,
            $notification
                ->user
                ->id
        );

        $this->assertSame(
            $appointment->id,
            $notification
                ->appointment
                ->id
        );
    }

    public function test_semaphore_gateway_submits_expected_form_payload(): void
    {
        config([
            'sms.semaphore.api_key' =>
                'fake-api-key',

            'sms.semaphore.sender_name' =>
                'StyleHub',

            'sms.semaphore.endpoint' =>
                'https://api.semaphore.co/api/v4/messages',
        ]);

        Http::fake([
            'api.semaphore.co/*' =>
                Http::response(
                    [
                        [
                            'message_id' =>
                                123456,

                            'recipient' =>
                                '09171234567',

                            'message' =>
                                'StyleHub appointment confirmed.',

                            'sender_name' =>
                                'StyleHub',

                            'status' =>
                                'Queued',
                        ],
                    ],
                    200
                ),
        ]);

        $gateway =
            new SemaphoreSmsGateway();

        $result =
            $gateway->send(
                number:
                    '09171234567',

                message:
                    'StyleHub appointment confirmed.',
            );

        $this->assertSame(
            '123456',
            $result->providerMessageId
        );

        $this->assertSame(
            'Queued',
            $result->providerStatus
        );

        Http::assertSent(
            function (
                Request $request
            ): bool {
                $data =
                    $request->data();

                return $request->url()
                    === 'https://api.semaphore.co/api/v4/messages'
                    && $request->method()
                    === 'POST'
                    && (
                        $data['apikey']
                        ?? null
                    ) === 'fake-api-key'
                    && (
                        $data['number']
                        ?? null
                    ) === '09171234567'
                    && (
                        $data['message']
                        ?? null
                    ) === 'StyleHub appointment confirmed.'
                    && (
                        $data['sendername']
                        ?? null
                    ) === 'StyleHub';
            }
        );
    }

    public function test_semaphore_failed_status_is_rejected(): void
    {
        config([
            'sms.semaphore.api_key' =>
                'fake-api-key',
        ]);

        Http::fake([
            '*' =>
                Http::response(
                    [
                        [
                            'message_id' =>
                                123456,

                            'status' =>
                                'Failed',
                        ],
                    ],
                    200
                ),
        ]);

        $this->expectException(
            SmsGatewayException::class
        );

        $this->expectExceptionMessage(
            'Semaphore rejected the SMS message.'
        );

        (
            new SemaphoreSmsGateway()
        )->send(
            number:
                '09171234567',

            message:
                'StyleHub message.',
        );
    }

    public function test_sms_job_marks_provider_accepted_message_as_accepted(): void
    {
        config([
            'sms.enabled' =>
                true,

            'sms.semaphore.api_key' =>
                'fake-api-key',
        ]);

        Http::fake([
            '*' =>
                Http::response(
                    [
                        [
                            'message_id' =>
                                987654,

                            'status' =>
                                'Pending',
                        ],
                    ],
                    200
                ),
        ]);

        $notification =
            SmsNotification::factory()
                ->create();

        $job =
            new SendSmsNotification(
                $notification->id
            );

        $job->handle(
            new SemaphoreSmsGateway()
        );

        $updated =
            $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Accepted,
            $updated->status
        );

        $this->assertSame(
            '987654',
            $updated->provider_message_id
        );

        $this->assertSame(
            'Pending',
            $updated->provider_status
        );

        $this->assertSame(
            1,
            $updated->attempts
        );

        $this->assertNotNull(
            $updated->accepted_at
        );

        $this->assertNull(
            $updated->failed_at
        );

        $this->assertNull(
            $updated->last_error
        );
    }

    public function test_sms_job_records_provider_failure_without_deleting_notification(): void
    {
        config([
            'sms.enabled' =>
                true,

            'sms.semaphore.api_key' =>
                'fake-api-key',
        ]);

        Http::fake([
            '*' =>
                Http::response(
                    [
                        [
                            'message_id' =>
                                123456,

                            'status' =>
                                'Failed',
                        ],
                    ],
                    200
                ),
        ]);

        $notification =
            SmsNotification::factory()
                ->create();

        $job =
            new SendSmsNotification(
                $notification->id
            );

        $job->handle(
            new SemaphoreSmsGateway()
        );

        $updated =
            $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Failed,
            $updated->status
        );

        $this->assertSame(
            1,
            $updated->attempts
        );

        $this->assertNotNull(
            $updated->failed_at
        );

        $this->assertSame(
            'Semaphore rejected the SMS message.',
            $updated->last_error
        );

        $this->assertDatabaseHas(
            'sms_notifications',
            [
                'id' =>
                    $notification->id,
            ]
        );
    }

    public function test_disabled_sms_job_is_safely_skipped_without_http_request(): void
    {
        config([
            'sms.enabled' =>
                false,
        ]);

        Http::fake();

        $notification =
            SmsNotification::factory()
                ->create();

        $job =
            new SendSmsNotification(
                $notification->id
            );

        $job->handle(
            new SemaphoreSmsGateway()
        );

        $updated =
            $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Skipped,
            $updated->status
        );

        $this->assertSame(
            1,
            $updated->attempts
        );

        $this->assertSame(
            'SMS sending is disabled.',
            $updated->last_error
        );

        Http::assertNothingSent();
    }
}