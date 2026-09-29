<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Jobs\SendSmsNotification;
use App\Models\AuditLog;
use App\Models\SmsNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminSmsRetryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_retry_requires_authentication(): void
    {
        Queue::fake();

        $notification =
            SmsNotification::factory()
                ->failed()
                ->create();

        $this->postJson(
            "/api/v1/admin/sms-notifications/{$notification->id}/retry"
        )->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_customer_and_shop_owner_cannot_retry_sms(): void
    {
        Queue::fake();

        foreach (
            [
                User::factory()
                    ->customer()
                    ->create(),

                User::factory()
                    ->shopOwner()
                    ->create(),
            ] as $user
        ) {
            $notification =
                SmsNotification::factory()
                    ->failed()
                    ->create();

            $this->actingAs(
                $user,
                'sanctum'
            )
                ->postJson(
                    "/api/v1/admin/sms-notifications/{$notification->id}/retry"
                )
                ->assertForbidden();
        }

        Queue::assertNothingPushed();
    }

    public function test_suspended_admin_cannot_retry_sms(): void
    {
        Queue::fake();

        $admin =
            User::factory()
                ->admin()
                ->suspended()
                ->create();

        $notification =
            SmsNotification::factory()
                ->failed()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                "/api/v1/admin/sms-notifications/{$notification->id}/retry"
            )
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_admin_can_queue_controlled_retry_for_definite_failure(): void
    {
        Queue::fake();

        $admin =
            User::factory()
                ->admin()
                ->create();

        $notification =
            SmsNotification::factory()
                ->failed()
                ->create([
                    'provider_message_id' =>
                        'SEM-FAILED-100',

                    'provider_status' =>
                        'Failed',
                ]);

        $attemptsBefore =
            $notification->attempts;

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                "/api/v1/admin/sms-notifications/{$notification->id}/retry"
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.sms_notification.status',
                SmsNotificationStatus::Pending
                    ->value
            )
            ->assertJsonPath(
                'data.sms_notification.failure_kind',
                null
            )
            ->assertJsonPath(
                'data.sms_notification.can_retry',
                false
            );

        $updated =
            $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Pending,
            $updated->status
        );

        $this->assertNull(
            $updated->failure_kind
        );

        $this->assertSame(
            $attemptsBefore,
            $updated->attempts
        );

        $this->assertNull(
            $updated->provider_message_id
        );

        $this->assertNull(
            $updated->provider_status
        );

        $this->assertNull(
            $updated->accepted_at
        );

        $this->assertNull(
            $updated->failed_at
        );

        $this->assertNull(
            $updated->last_error
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

    public function test_admin_retry_is_audited_against_sms_notification(): void
    {
        Queue::fake();

        $admin =
            User::factory()
                ->admin()
                ->create();

        $notification =
            SmsNotification::factory()
                ->failed()
                ->create();

        $attemptsBefore =
            $notification->attempts;

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                "/api/v1/admin/sms-notifications/{$notification->id}/retry"
            )
            ->assertOk();

        $log =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::SmsRetryQueued
                        ->value
                )
                ->where(
                    'auditable_id',
                    $notification->id
                )
                ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $log->actor_id
        );

        $this->assertSame(
            'admin',
            $log->actor_role
        );

        $this->assertSame(
            'admin_api',
            $log->metadata['source']
        );

        $this->assertSame(
            $attemptsBefore,
            $log->metadata['attempts_before_retry']
        );

        $this->assertSame(
            SmsNotificationStatus::Failed->value,
            $log->metadata['previous_status']
        );

        $this->assertSame(
            SmsNotificationStatus::Pending->value,
            $log->metadata['new_status']
        );

        $this->assertSame(
            SmsFailureKind::Definite->value,
            $log->metadata['previous_failure_kind']
        );
    }

    public function test_uncertain_failure_cannot_be_retried_by_admin(): void
    {
        Queue::fake();

        $admin =
            User::factory()
                ->admin()
                ->create();

        $notification =
            SmsNotification::factory()
                ->uncertainFailure()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                "/api/v1/admin/sms-notifications/{$notification->id}/retry"
            )
            ->assertConflict();

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

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_non_failed_sms_states_cannot_be_retried(): void
    {
        Queue::fake();

        $admin =
            User::factory()
                ->admin()
                ->create();

        $notifications = [
            SmsNotification::factory()
                ->create(),

            SmsNotification::factory()
                ->accepted()
                ->create(),

            SmsNotification::factory()
                ->create([
                    'status' =>
                        SmsNotificationStatus::Skipped,
                ]),
        ];

        foreach (
            $notifications as $notification
        ) {
            $this->actingAs(
                $admin,
                'sanctum'
            )
                ->postJson(
                    "/api/v1/admin/sms-notifications/{$notification->id}/retry"
                )
                ->assertConflict();
        }

        Queue::assertNothingPushed();

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_second_retry_request_is_rejected_after_first_retry_queues_sms(): void
    {
        Queue::fake();

        $admin =
            User::factory()
                ->admin()
                ->create();

        $notification =
            SmsNotification::factory()
                ->failed()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        );

        $this->postJson(
            "/api/v1/admin/sms-notifications/{$notification->id}/retry"
        )->assertOk();

        $this->postJson(
            "/api/v1/admin/sms-notifications/{$notification->id}/retry"
        )->assertConflict();

        Queue::assertPushedTimes(
            SendSmsNotification::class,
            1
        );

        $this->assertDatabaseCount(
            'audit_logs',
            1
        );
    }

    public function test_admin_cannot_inject_retry_fields(): void
    {
        Queue::fake();

        $admin =
            User::factory()
                ->admin()
                ->create();

        $notification =
            SmsNotification::factory()
                ->failed()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                "/api/v1/admin/sms-notifications/{$notification->id}/retry",
                [
                    'force' =>
                        true,

                    'recipient' =>
                        '09170000000',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'force',
                'recipient',
            ]);

        $updated =
            $notification->fresh();

        $this->assertSame(
            SmsNotificationStatus::Failed,
            $updated->status
        );

        $this->assertSame(
            SmsFailureKind::Definite,
            $updated->failure_kind
        );

        Queue::assertNothingPushed();
    }

    public function test_nonexistent_sms_retry_returns_not_found(): void
    {
        Queue::fake();

        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                '/api/v1/admin/sms-notifications/999999/retry'
            )
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}