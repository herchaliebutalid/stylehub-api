<?php

namespace Tests\Feature;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Models\SmsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemaphoreLiveTestCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sms.enabled' =>
                false,

            'sms.live_test.enabled' =>
                true,

            'sms.live_test.cooldown_minutes' =>
                10,

            'sms.semaphore.api_key' =>
                'fake-api-key',

            'sms.semaphore.sender_name' =>
                'StyleHub',

            'sms.semaphore.endpoint' =>
                'https://api.semaphore.co/api/v4/messages',
        ]);
    }

    public function test_controlled_test_refuses_when_live_test_gate_is_disabled(): void
    {
        config([
            'sms.live_test.enabled' =>
                false,
        ]);

        Http::fake();

        $this->artisan(
            'stylehub:sms:test',
            [
                '--number' =>
                    '09171234567',

                '--yes' =>
                    true,
            ]
        )->assertExitCode(
            1
        );

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );
    }

    public function test_controlled_test_refuses_when_normal_sms_is_enabled(): void
    {
        config([
            'sms.enabled' =>
                true,
        ]);

        Http::fake();

        $this->artisan(
            'stylehub:sms:test',
            [
                '--number' =>
                    '09171234567',

                '--yes' =>
                    true,
            ]
        )->assertExitCode(
            1
        );

        Http::assertNothingSent();
    }

    public function test_invalid_mobile_number_is_rejected_without_http_request(): void
    {
        Http::fake();

        $this->artisan(
            'stylehub:sms:test',
            [
                '--number' =>
                    '12345',

                '--yes' =>
                    true,
            ]
        )->assertExitCode(
            1
        );

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            0
        );
    }

    public function test_controlled_test_normalizes_number_and_sends_exactly_one_request(): void
    {
        Http::fake([
            'api.semaphore.co/*' =>
                Http::response(
                    [
                        [
                            'message_id' =>
                                555001,

                            'status' =>
                                'Queued',
                        ],
                    ],
                    200
                ),
        ]);

        $this->artisan(
            'stylehub:sms:test',
            [
                '--number' =>
                    '+639171234567',

                '--yes' =>
                    true,
            ]
        )->assertSuccessful();

        Http::assertSentCount(
            1
        );

        $notification =
            SmsNotification::query()
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
            SmsNotificationStatus::Accepted,
            $notification->status
        );

        $this->assertSame(
            '555001',
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

        $this->assertStringStartsNotWith(
            'TEST',
            $notification->message
        );
    }

    public function test_definite_provider_failure_is_recorded(): void
    {
        Http::fake([
            '*' =>
                Http::response(
                    [
                        'message' =>
                            'Rejected',
                    ],
                    400
                ),
        ]);

        $this->artisan(
            'stylehub:sms:test',
            [
                '--number' =>
                    '09171234567',

                '--yes' =>
                    true,
            ]
        )->assertExitCode(
            1
        );

        Http::assertSentCount(
            1
        );

        $notification =
            SmsNotification::query()
                ->firstOrFail();

        $this->assertSame(
            SmsNotificationStatus::Failed,
            $notification->status
        );

        $this->assertSame(
            SmsFailureKind::Definite,
            $notification->failure_kind
        );
    }

    public function test_recent_uncertain_test_blocks_another_send(): void
    {
        Http::fake();

        SmsNotification::factory()
            ->uncertainFailure()
            ->create([
                'type' =>
                    SmsNotificationType::SystemTest,

                'recipient' =>
                    '09171234567',
            ]);

        $this->artisan(
            'stylehub:sms:test',
            [
                '--number' =>
                    '09171234567',

                '--yes' =>
                    true,
            ]
        )->assertExitCode(
            1
        );

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'sms_notifications',
            1
        );
    }
}