<?php

namespace Tests\Feature;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Exceptions\Sms\SmsGatewayException;
use App\Jobs\SendSmsNotification;
use App\Models\Appointment;
use App\Models\SmsNotification;
use App\Models\User;
use App\Services\Sms\TextBeeSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsNotificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const TEXTBEE_TEST_ENDPOINT =
        'https://textbee.test/api/v1/gateway/send-sms';

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent tests from contacting any real SMS gateway.
        Http::preventStrayRequests();

        config([
            'sms.enabled' => true,
            'sms.textbee.endpoint' => self::TEXTBEE_TEST_ENDPOINT,
            'sms.textbee.api_key' => 'fake-textbee-test-key',
            'sms.textbee.device_id' => 'fake-test-device-id',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SMS Notification Factory
    |--------------------------------------------------------------------------
    */

    public function test_sms_notification_factory_creates_pending_textbee_record(): void
    {
        $notification = SmsNotification::factory()->create();

        $this->assertSame(
            SmsNotificationType::BookingReceived,
            $notification->type
        );

        $this->assertSame(
            SmsNotificationStatus::Pending,
            $notification->status
        );

        $this->assertSame(
            'textbee',
            $notification->provider
        );

        $this->assertSame(
            0,
            $notification->attempts
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment Security
    |--------------------------------------------------------------------------
    */

    public function test_sensitive_sms_delivery_fields_cannot_be_mass_assigned(): void
    {
        $notification = new SmsNotification();

        $notification->fill([
            'user_id' => 999,
            'appointment_id' => 999,
            'type' => SmsNotificationType::BookingReceived->value,
            'recipient' => '09171234567',
            'message' => 'Safe StyleHub notification.',
            'status' => SmsNotificationStatus::Accepted->value,
            'provider_message_id' => 'malicious-id',
            'provider_status' => 'Sent',
        ]);

        $this->assertNull($notification->user_id);
        $this->assertNull($notification->appointment_id);
        $this->assertNull($notification->provider_message_id);
        $this->assertNull($notification->provider_status);

        $this->assertSame(
            '09171234567',
            $notification->recipient
        );

        $this->assertSame(
            'Safe StyleHub notification.',
            $notification->message
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function test_sms_notification_belongs_to_user_and_appointment(): void
    {
        $user = User::factory()
            ->customer()
            ->create();

        $appointment = Appointment::factory()->create([
            'customer_id' => $user->id,
        ]);

        $notification = SmsNotification::factory()->create([
            'user_id' => $user->id,
            'appointment_id' => $appointment->id,
        ]);

        $this->assertSame(
            $user->id,
            $notification->user->id
        );

        $this->assertSame(
            $appointment->id,
            $notification->appointment->id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TextBee JSON Request
    |--------------------------------------------------------------------------
    */

    public function test_textbee_gateway_posts_json_with_e164_recipient_and_device_id(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'data' => [
                    'success' => true,
                    'message' => 'SMS added to queue for processing',
                    'smsBatchId' => 'test-batch-123',
                    'recipientCount' => 1,
                ],
            ], 200),
        ]);

        $result = (new TextBeeSmsGateway())->send(
            number: '09171234567',
            message: 'StyleHub appointment confirmed.',
        );

        $this->assertSame(
            'test-batch-123',
            $result->providerMessageId
        );

        $this->assertSame(
            'Queued',
            $result->providerStatus
        );

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === self::TEXTBEE_TEST_ENDPOINT
                && $request->hasHeader(
                    'x-api-key',
                    'fake-textbee-test-key'
                )
                && $request->data() === [
                    'recipients' => [
                        '+639171234567',
                    ],
                    'message' => 'StyleHub appointment confirmed.',
                    'deviceId' => 'fake-test-device-id',
                ];
        });

        Http::assertSentCount(1);
    }

    /*
    |--------------------------------------------------------------------------
    | Immediate Dispatch Success
    |--------------------------------------------------------------------------
    */

    public function test_textbee_gateway_accepts_immediate_dispatch_result(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'data' => [
                    'successCount' => 1,
                    'failureCount' => 0,
                ],
            ], 200),
        ]);

        $result = (new TextBeeSmsGateway())->send(
            number: '09171234567',
            message: 'StyleHub message.',
        );

        $this->assertNull(
            $result->providerMessageId
        );

        $this->assertSame(
            'Dispatched',
            $result->providerStatus
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Explicit Provider Rejection
    |--------------------------------------------------------------------------
    */

    public function test_textbee_gateway_explicit_rejection_is_definite(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'data' => [
                    'success' => false,
                ],
            ], 200),
        ]);

        try {
            (new TextBeeSmsGateway())->send(
                '09171234567',
                'StyleHub message.'
            );

            $this->fail(
                'TextBee should reject an explicit unsuccessful response.'
            );
        } catch (SmsGatewayException $exception) {
            $this->assertSame(
                SmsFailureKind::Definite,
                $exception->failureKind
            );

            $this->assertTrue(
                $exception->isSafeToRetry()
            );

            $this->assertSame(
                'TextBee rejected the SMS message.',
                $exception->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP 401 Definite Failure
    |--------------------------------------------------------------------------
    */

    public function test_textbee_gateway_http_401_is_definite(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'message' => 'Unauthorized',
            ], 401),
        ]);

        try {
            (new TextBeeSmsGateway())->send(
                '09171234567',
                'StyleHub message.'
            );

            $this->fail(
                'TextBee HTTP 401 must be rejected.'
            );
        } catch (SmsGatewayException $exception) {
            $this->assertSame(
                SmsFailureKind::Definite,
                $exception->failureKind
            );

            $this->assertTrue(
                $exception->isSafeToRetry()
            );

            $this->assertSame(
                'TextBee rejected the request with HTTP status 401.',
                $exception->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP 503 Uncertain Failure
    |--------------------------------------------------------------------------
    */

    public function test_textbee_gateway_http_503_is_uncertain(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'message' => 'Unavailable',
            ], 503),
        ]);

        try {
            (new TextBeeSmsGateway())->send(
                '09171234567',
                'StyleHub message.'
            );

            $this->fail(
                'TextBee HTTP 503 must not be treated as definite.'
            );
        } catch (SmsGatewayException $exception) {
            $this->assertSame(
                SmsFailureKind::Uncertain,
                $exception->failureKind
            );

            $this->assertFalse(
                $exception->isSafeToRetry()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Incomplete Success Response
    |--------------------------------------------------------------------------
    */

    public function test_textbee_gateway_incomplete_success_response_is_uncertain(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'data' => [
                    'success' => true,
                ],
            ], 200),
        ]);

        try {
            (new TextBeeSmsGateway())->send(
                '09171234567',
                'StyleHub message.'
            );

            $this->fail(
                'Incomplete TextBee success responses must not be assumed delivered.'
            );
        } catch (SmsGatewayException $exception) {
            $this->assertSame(
                SmsFailureKind::Uncertain,
                $exception->failureKind
            );

            $this->assertFalse(
                $exception->isSafeToRetry()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Queued SMS Accepted by TextBee
    |--------------------------------------------------------------------------
    */

    public function test_sms_job_marks_textbee_queued_result_as_accepted(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'data' => [
                    'success' => true,
                    'smsBatchId' => 'job-batch-987',
                ],
            ], 200),
        ]);

        $notification = SmsNotification::factory()->create();

        (new SendSmsNotification(
            $notification->id
        ))->handle(
            new TextBeeSmsGateway()
        );

        $updated = $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Accepted,
            $updated->status
        );

        $this->assertSame(
            'textbee',
            $updated->provider
        );

        $this->assertSame(
            'job-batch-987',
            $updated->provider_message_id
        );

        $this->assertSame(
            'Queued',
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
            $updated->failure_kind
        );

        $this->assertNull(
            $updated->last_error
        );

        Http::assertSentCount(1);
    }

    /*
    |--------------------------------------------------------------------------
    | Job Definite Failure
    |--------------------------------------------------------------------------
    */

    public function test_sms_job_records_definite_failure_without_deleting_notification(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'message' => 'Unauthorized',
            ], 401),
        ]);

        $notification = SmsNotification::factory()->create();

        (new SendSmsNotification(
            $notification->id
        ))->handle(
            new TextBeeSmsGateway()
        );

        $updated = $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Failed,
            $updated->status
        );

        $this->assertSame(
            SmsFailureKind::Definite,
            $updated->failure_kind
        );

        $this->assertTrue(
            $updated->isSafeToRetry()
        );

        $this->assertSame(
            1,
            $updated->attempts
        );

        $this->assertNotNull(
            $updated->failed_at
        );

        $this->assertNull(
            $updated->accepted_at
        );

        $this->assertSame(
            'TextBee rejected the request with HTTP status 401.',
            $updated->last_error
        );

        $this->assertDatabaseHas(
            'sms_notifications',
            [
                'id' => $notification->id,
            ]
        );

        Http::assertSentCount(1);
    }

    /*
    |--------------------------------------------------------------------------
    | Job Uncertain Failure
    |--------------------------------------------------------------------------
    */

    public function test_sms_job_records_uncertain_failure_without_auto_retry(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'message' => 'Unavailable',
            ], 503),
        ]);

        $notification = SmsNotification::factory()->create();

        (new SendSmsNotification(
            $notification->id
        ))->handle(
            new TextBeeSmsGateway()
        );

        $updated = $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Failed,
            $updated->status
        );

        $this->assertSame(
            SmsFailureKind::Uncertain,
            $updated->failure_kind
        );

        $this->assertFalse(
            $updated->isSafeToRetry()
        );

        $this->assertSame(
            1,
            $updated->attempts
        );

        $this->assertNotNull(
            $updated->failed_at
        );

        $this->assertNull(
            $updated->accepted_at
        );

        Http::assertSentCount(1);
    }

    /*
    |--------------------------------------------------------------------------
    | SMS Master Switch Disabled
    |--------------------------------------------------------------------------
    */

    public function test_disabled_sms_job_is_skipped_without_http_request(): void
    {
        config([
            'sms.enabled' => false,
        ]);

        Http::fake();

        $notification = SmsNotification::factory()->create();

        (new SendSmsNotification(
            $notification->id
        ))->handle(
            new TextBeeSmsGateway()
        );

        $updated = $notification->fresh();

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

    /*
    |--------------------------------------------------------------------------
    | Duplicate Job Prevention
    |--------------------------------------------------------------------------
    */

    public function test_completed_notification_is_not_sent_again(): void
    {
        Http::fake();

        $notification = SmsNotification::factory()
            ->accepted()
            ->create();

        (new SendSmsNotification(
            $notification->id
        ))->handle(
            new TextBeeSmsGateway()
        );

        $updated = $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Accepted,
            $updated->status
        );

        $this->assertSame(
            1,
            $updated->attempts
        );

        Http::assertNothingSent();
    }
}
