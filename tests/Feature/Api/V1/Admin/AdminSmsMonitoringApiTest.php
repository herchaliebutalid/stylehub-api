<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Models\Appointment;
use App\Models\Shop;
use App\Models\SmsNotification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSmsMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_monitoring_requires_authentication(): void
    {
        $notification =
            SmsNotification::factory()
                ->create();

        $this->getJson(
            '/api/v1/admin/sms-notifications'
        )->assertUnauthorized();

        $this->getJson(
            '/api/v1/admin/sms-notifications/summary'
        )->assertUnauthorized();

        $this->getJson(
            "/api/v1/admin/sms-notifications/{$notification->id}"
        )->assertUnauthorized();
    }

    public function test_customer_and_shop_owner_cannot_access_sms_monitoring(): void
    {
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
            $this->actingAs(
                $user,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/admin/sms-notifications'
                )
                ->assertForbidden();
        }
    }

    public function test_suspended_admin_cannot_access_sms_monitoring(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->suspended()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/sms-notifications'
            )
            ->assertForbidden();
    }

    public function test_admin_can_browse_sms_notifications_with_masked_recipient(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $notification =
            SmsNotification::factory()
                ->accepted()
                ->create([
                    'recipient' =>
                        '09171234567',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/sms-notifications'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.sms_notifications.0.id',
                $notification->id
            )
            ->assertJsonPath(
                'data.sms_notifications.0.recipient',
                '0917****567'
            )
            ->assertJsonPath(
                'data.sms_notifications.0.status',
                SmsNotificationStatus::Accepted
                    ->value
            )
            ->assertJsonPath(
                'data.sms_notifications.0.can_retry',
                false
            )
            ->assertJsonMissingPath(
                'data.sms_notifications.0.message'
            )
            ->assertJsonMissingPath(
                'data.sms_notifications.0.provider_message_id'
            );
    }

    public function test_admin_summary_reports_delivery_health_and_safe_retry_counts(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        SmsNotification::factory()
            ->create();

        SmsNotification::factory()
            ->accepted()
            ->count(2)
            ->create();

        SmsNotification::factory()
            ->failed()
            ->create();

        SmsNotification::factory()
            ->uncertainFailure()
            ->create();

        SmsNotification::factory()
            ->create([
                'status' =>
                    SmsNotificationStatus::Skipped,

                'last_error' =>
                    'Recipient mobile number is unavailable.',
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/sms-notifications/summary'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total',
                6
            )
            ->assertJsonPath(
                'data.summary.pending',
                1
            )
            ->assertJsonPath(
                'data.summary.processing',
                0
            )
            ->assertJsonPath(
                'data.summary.accepted',
                2
            )
            ->assertJsonPath(
                'data.summary.failed',
                2
            )
            ->assertJsonPath(
                'data.summary.skipped',
                1
            )
            ->assertJsonPath(
                'data.summary.safe_to_retry',
                1
            )
            ->assertJsonPath(
                'data.summary.uncertain_failures',
                1
            );
    }

    public function test_admin_can_filter_sms_by_status_type_failure_kind_and_provider(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $matching =
            SmsNotification::factory()
                ->failed()
                ->create([
                    'type' =>
                        SmsNotificationType::AppointmentReminder,

                    'provider' =>
                        'semaphore',
                ]);

        SmsNotification::factory()
            ->accepted()
            ->create([
                'type' =>
                    SmsNotificationType::AppointmentReminder,
            ]);

        SmsNotification::factory()
            ->uncertainFailure()
            ->create([
                'type' =>
                    SmsNotificationType::AppointmentReminder,
            ]);

        $query =
            http_build_query([
                'status' =>
                    SmsNotificationStatus::Failed
                        ->value,

                'type' =>
                    SmsNotificationType::AppointmentReminder
                        ->value,

                'failure_kind' =>
                    SmsFailureKind::Definite
                        ->value,

                'provider' =>
                    'semaphore',
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/sms-notifications?{$query}"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.sms_notifications'
            )
            ->assertJsonPath(
                'data.sms_notifications.0.id',
                $matching->id
            )
            ->assertJsonPath(
                'data.sms_notifications.0.can_retry',
                true
            );
    }

    public function test_admin_can_filter_sms_by_user_and_appointment(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        /*
         * This test is verifying the Admin monitoring filter,
         * not the appointment SMS workflow.
         *
         * Create the appointment quietly so Appointment observers
         * do not automatically create an additional SMS notification
         * with the same user_id and appointment_id.
         */
        $appointment =
            Appointment::factory()
                ->createQuietly([
                    'customer_id' =>
                        $customer->id,
                ]);

        $matching =
            SmsNotification::factory()
                ->create([
                    'user_id' =>
                        $customer->id,

                    'appointment_id' =>
                        $appointment->id,
                ]);

        SmsNotification::factory()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/sms-notifications?user_id='
                .$customer->id
                .'&appointment_id='
                .$appointment->id
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.sms_notifications'
            )
            ->assertJsonPath(
                'data.sms_notifications.0.id',
                $matching->id
            );
    }

    public function test_admin_can_search_sms_operational_fields(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create([
                    'first_name' =>
                        'Cassandra',

                    'last_name' =>
                        'Santos',
                ]);

        $matching =
            SmsNotification::factory()
                ->accepted()
                ->create([
                    'user_id' =>
                        $customer->id,

                    'provider_message_id' =>
                        'SEM-TRACK-8821',
                ]);

        SmsNotification::factory()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/sms-notifications?search=Cassandra'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.sms_notifications'
            )
            ->assertJsonPath(
                'data.sms_notifications.0.id',
                $matching->id
            );

        $this->getJson(
            '/api/v1/admin/sms-notifications?search=SEM-TRACK-8821'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.sms_notifications'
            )
            ->assertJsonPath(
                'data.sms_notifications.0.id',
                $matching->id
            );
    }

    public function test_admin_can_filter_sms_by_created_date_range(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $inside =
            SmsNotification::factory()
                ->create([
                    'created_at' =>
                        CarbonImmutable::parse(
                            '2026-09-10 10:00:00'
                        ),
                ]);

        SmsNotification::factory()
            ->create([
                'created_at' =>
                    CarbonImmutable::parse(
                        '2026-09-08 10:00:00'
                    ),
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/sms-notifications?date_from=2026-09-10&date_to=2026-09-10'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.sms_notifications'
            )
            ->assertJsonPath(
                'data.sms_notifications.0.id',
                $inside->id
            );
    }

    public function test_admin_can_view_sms_detail_with_diagnostics_but_without_credentials(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        /*
         * Keep this detail test isolated from the Appointment SMS observer.
         */
        $appointment =
            Appointment::factory()
                ->createQuietly([
                    'customer_id' =>
                        $customer->id,

                    'shop_id' =>
                        $shop->id,
                ]);

        $notification =
            SmsNotification::factory()
                ->failed()
                ->create([
                    'user_id' =>
                        $customer->id,

                    'appointment_id' =>
                        $appointment->id,

                    'recipient' =>
                        '09171234567',

                    'message' =>
                        'StyleHub: Appointment reminder.',

                    'provider_message_id' =>
                        'SEM-123456',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/sms-notifications/{$notification->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.sms_notification.recipient',
                '09171234567'
            )
            ->assertJsonPath(
                'data.sms_notification.message',
                'StyleHub: Appointment reminder.'
            )
            ->assertJsonPath(
                'data.sms_notification.provider_message_id',
                'SEM-123456'
            )
            ->assertJsonPath(
                'data.sms_notification.can_retry',
                true
            )
            ->assertJsonPath(
                'data.sms_notification.appointment.shop.id',
                $shop->id
            )
            ->assertJsonMissingPath(
                'data.sms_notification.user.password'
            )
            ->assertJsonMissingPath(
                'data.sms_notification.user.remember_token'
            )
            ->assertJsonMissingPath(
                'data.sms_notification.api_key'
            );
    }

    public function test_invalid_and_unknown_sms_monitoring_filters_are_rejected(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/sms-notifications?status=delivered'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->getJson(
            '/api/v1/admin/sms-notifications?secret=anything'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'secret',
            ]);

        $this->getJson(
            '/api/v1/admin/sms-notifications?per_page=1000'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_nonexistent_sms_notification_returns_not_found(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/sms-notifications/999999'
            )
            ->assertNotFound();
    }
}