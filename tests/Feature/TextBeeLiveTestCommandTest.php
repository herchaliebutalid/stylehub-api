<?php

namespace Tests\Feature;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Models\SmsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TextBeeLiveTestCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ENDPOINT =
        'https://textbee.test/api/v1/gateway/send-sms';

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent unexpected real network requests.
        Http::preventStrayRequests();

        config([
            'sms.enabled' => false,
            'sms.live_test.enabled' => true,
            'sms.live_test.cooldown_minutes' => 10,
            'sms.provider' => 'textbee',
            'sms.textbee.api_key' => 'fake-textbee-test-key',
            'sms.textbee.device_id' => 'fake-android-device-id',
            'sms.textbee.endpoint' => self::TEST_ENDPOINT,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Safety Gate: Live Testing Disabled
    |--------------------------------------------------------------------------
    */

    public function test_live_test_is_blocked_when_its_gate_is_disabled(): void
    {
        config([
            'sms.live_test.enabled' => false,
        ]);

        Http::fake();

        $this->artisan('stylehub:sms:test', [
            '--number' => '09171234567',
            '--yes' => true,
        ])->assertExitCode(1);

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Safety Gate: Normal SMS Enabled
    |--------------------------------------------------------------------------
    */

    public function test_live_test_is_blocked_when_normal_sms_is_enabled(): void
    {
        config([
            'sms.enabled' => true,
        ]);

        Http::fake();

        $this->artisan('stylehub:sms:test', [
            '--number' => '09171234567',
            '--yes' => true,
        ])->assertExitCode(1);

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Missing TextBee API Key
    |--------------------------------------------------------------------------
    */

    public function test_missing_textbee_api_key_blocks_test_without_request(): void
    {
        config([
            'sms.textbee.api_key' => null,
        ]);

        Http::fake();

        $this->artisan('stylehub:sms:test', [
            '--number' => '09171234567',
            '--yes' => true,
        ])->assertExitCode(1);

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Missing Android Device ID
    |--------------------------------------------------------------------------
    */

    public function test_missing_textbee_device_id_blocks_test_without_request(): void
    {
        config([
            'sms.textbee.device_id' => null,
        ]);

        Http::fake();

        $this->artisan('stylehub:sms:test', [
            '--number' => '09171234567',
            '--yes' => true,
        ])->assertExitCode(1);

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Invalid Philippine Phone Number
    |--------------------------------------------------------------------------
    */

    public function test_invalid_mobile_number_is_rejected_without_http_request(): void
    {
        Http::fake();

        $this->artisan('stylehub:sms:test', [
            '--number' => '12345',
            '--yes' => true,
        ])->assertExitCode(1);

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Interactive Confirmation
    |--------------------------------------------------------------------------
    */

    public function test_cancelling_interactive_confirmation_sends_nothing(): void
    {
        Http::fake();

        $this->artisan('stylehub:sms:test', [
            '--number' => '09171234567',
        ])
            ->expectsConfirmation(
                'Send exactly one real SMS now?',
                'no'
            )
            ->assertSuccessful();

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Successful TextBee Request
    |--------------------------------------------------------------------------
    */

    public function test_textbee_success_records_one_accepted_message_and_exact_request(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'data' => [
                    'success' => true,
                    'smsBatchId' => 'test-batch-555001',
                    'recipientCount' => 1,
                ],
            ], 200),
        ]);

        $this->artisan('stylehub:sms:test', [
            '--number' => '+639171234567',
            '--yes' => true,
        ])->assertSuccessful();

        // The command must submit exactly one request.
        Http::assertSentCount(1);

        Http::assertSent(
            function (Request $request): bool {
                $data = $request->data();

                return $request->method() === 'POST'
                    && $request->url() === self::TEST_ENDPOINT
                    && $request->hasHeader(
                        'x-api-key',
                        'fake-textbee-test-key'
                    )
                    && ($data['recipients'] ?? null) === [
                        '+639171234567',
                    ]
                    && ($data['deviceId'] ?? null) ===
                        'fake-android-device-id'
                    && ($data['message'] ?? null) ===
                        'StyleHub SMS verification: your TextBee connection is working. No action is required.';
            }
        );

        $this->assertDatabaseCount(
            'sms_notifications',
            1
        );

        $notification = SmsNotification::query()
            ->firstOrFail();

        $this->assertSame(
            SmsNotificationType::SystemTest,
            $notification->type
        );

        $this->assertSame(
            '09171234567',
            $notification->recipient
        );

        $this->assertSame(
            'textbee',
            $notification->provider
        );

        $this->assertSame(
            SmsNotificationStatus::Accepted,
            $notification->status
        );

        $this->assertSame(
            'test-batch-555001',
            $notification->provider_message_id
        );

        $this->assertSame(
            'Queued',
            $notification->provider_status
        );

        $this->assertSame(
            1,
            $notification->attempts
        );

        $this->assertNotNull(
            $notification->accepted_at
        );

        $this->assertNull(
            $notification->failed_at
        );

        $this->assertNull(
            $notification->failure_kind
        );

        $this->assertStringStartsWith(
            'StyleHub SMS verification:',
            $notification->message
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Definite Failure: HTTP 400
    |--------------------------------------------------------------------------
    */

    public function test_http_400_is_recorded_as_definite_failure(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'message' => 'Invalid request',
            ], 400),
        ]);

        $this->artisan('stylehub:sms:test', [
            '--number' => '09171234567',
            '--yes' => true,
        ])->assertExitCode(1);

        Http::assertSentCount(1);

        $this->assertDatabaseCount(
            'sms_notifications',
            1
        );

        $notification = SmsNotification::query()
            ->firstOrFail();

        $this->assertSame(
            'textbee',
            $notification->provider
        );

        $this->assertSame(
            SmsNotificationStatus::Failed,
            $notification->status
        );

        $this->assertSame(
            SmsFailureKind::Definite,
            $notification->failure_kind
        );

        $this->assertSame(
            1,
            $notification->attempts
        );

        $this->assertNotNull(
            $notification->failed_at
        );

        $this->assertNull(
            $notification->accepted_at
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Uncertain Failure: HTTP 503
    |--------------------------------------------------------------------------
    */

    public function test_http_503_is_recorded_as_uncertain_failure(): void
    {
        Http::fake([
            'textbee.test/*' => Http::response([
                'message' => 'Unavailable',
            ], 503),
        ]);

        $this->artisan('stylehub:sms:test', [
            '--number' => '09171234567',
            '--yes' => true,
        ])->assertExitCode(1);

        Http::assertSentCount(1);

        $notification = SmsNotification::query()
            ->firstOrFail();

        $this->assertSame(
            SmsNotificationStatus::Failed,
            $notification->status
        );

        $this->assertSame(
            SmsFailureKind::Uncertain,
            $notification->failure_kind
        );

        $this->assertFalse(
            $notification->isSafeToRetry()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Prevention: Uncertain Outcome
    |--------------------------------------------------------------------------
    */

    public function test_recent_uncertain_test_blocks_another_send(): void
    {
        Http::fake();

        SmsNotification::factory()
            ->uncertainFailure()
            ->create([
                'type' => SmsNotificationType::SystemTest,
                'recipient' => '09171234567',
            ]);

        $this->artisan('stylehub:sms:test', [
            '--number' => '09171234567',
            '--yes' => true,
        ])->assertExitCode(1);

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Prevention: Accepted SMS
    |--------------------------------------------------------------------------
    */

    public function test_recent_accepted_test_blocks_another_send(): void
    {
        Http::fake();

        SmsNotification::factory()
            ->accepted()
            ->create([
                'type' => SmsNotificationType::SystemTest,
                'recipient' => '09171234567',
            ]);

        $this->artisan('stylehub:sms:test', [
            '--number' => '+639171234567',
            '--yes' => true,
        ])->assertExitCode(1);

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            1
        );
    }
}
