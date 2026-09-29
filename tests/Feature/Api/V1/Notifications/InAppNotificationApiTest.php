<?php

namespace Tests\Feature\Api\V1\Notifications;

use App\Enums\InAppNotificationType;
use App\Models\Appointment;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InAppNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/notifications'
        )->assertUnauthorized();

        $this->getJson(
            '/api/v1/notifications/unread-count'
        )->assertUnauthorized();

        $this->patchJson(
            '/api/v1/notifications/read-all'
        )->assertUnauthorized();

        $this->patchJson(
            '/api/v1/notifications/1/read'
        )->assertUnauthorized();
    }

    public function test_admin_cannot_use_mobile_notification_api(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->getJson(
            '/api/v1/notifications'
        )->assertForbidden();
    }

    public function test_unverified_customer_cannot_use_notification_api(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/notifications'
        )->assertForbidden();
    }

    public function test_unverified_shop_owner_cannot_use_notification_api(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->unverifiedPhone()
                ->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/notifications'
        )->assertForbidden();
    }

    public function test_verified_shop_owner_can_use_account_notification_api_without_shop_management_route(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        InAppNotification::factory()
            ->create([
                'user_id' =>
                    $owner->id,

                'type' =>
                    InAppNotificationType::BookingReceived,

                'title' =>
                    'New Appointment',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/notifications'
        )
            ->assertOk()

            ->assertJsonCount(
                1,
                'data.notifications'
            );
    }

    public function test_customer_lists_only_own_notifications_newest_first_with_safe_fields(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        $appointment =
            Appointment::factory()
                ->create([
                    'customer_id' =>
                        $customer->id,
                ]);

        $oldest =
            InAppNotification::factory()
                ->create([
                    'user_id' =>
                        $customer->id,

                    'type' =>
                        InAppNotificationType::BookingReceived,

                    'title' =>
                        'Old Notification',

                    'created_at' =>
                        now()->subHours(
                            3
                        ),
                ]);

        $newest =
            InAppNotification::factory()
                ->create([
                    'user_id' =>
                        $customer->id,

                    'type' =>
                        InAppNotificationType::AppointmentConfirmed,

                    'title' =>
                        'Newest Notification',

                    'appointment_id' =>
                        $appointment->id,

                    'dedupe_key' =>
                        'safe-test-dedupe-key',

                    'created_at' =>
                        now(),
                ]);

        InAppNotification::factory()
            ->create([
                'user_id' =>
                    $otherCustomer->id,

                'title' =>
                    'Other Customer Notification',
            ]);

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )->getJson(
                '/api/v1/notifications?per_page=20'
            );

        $response
            ->assertOk()

            ->assertJsonCount(
                2,
                'data.notifications'
            )

            ->assertJsonPath(
                'data.notifications.0.id',
                $newest->id
            )

            ->assertJsonPath(
                'data.notifications.1.id',
                $oldest->id
            )

            ->assertJsonPath(
                'data.notifications.0.type',
                InAppNotificationType::AppointmentConfirmed
                    ->value
            )

            ->assertJsonPath(
                'data.notifications.0.target.kind',
                'appointment'
            )

            ->assertJsonPath(
                'data.notifications.0.target.id',
                $appointment->id
            )

            ->assertJsonMissing([
                'title' =>
                    'Other Customer Notification',
            ])

            ->assertJsonMissingPath(
                'data.notifications.0.user_id'
            )

            ->assertJsonMissingPath(
                'data.notifications.0.dedupe_key'
            );
    }

    public function test_notification_list_is_paginated(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        InAppNotification::factory()
            ->count(
                3
            )
            ->create([
                'user_id' =>
                    $customer->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/notifications?per_page=2'
        )
            ->assertOk()

            ->assertJsonCount(
                2,
                'data.notifications'
            )

            ->assertJsonPath(
                'data.pagination.current_page',
                1
            )

            ->assertJsonPath(
                'data.pagination.per_page',
                2
            )

            ->assertJsonPath(
                'data.pagination.total',
                3
            )

            ->assertJsonPath(
                'data.pagination.has_more_pages',
                true
            );
    }

    public function test_customer_can_filter_notifications_by_unread_status(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $unread =
            InAppNotification::factory()
                ->create([
                    'user_id' =>
                        $customer->id,

                    'title' =>
                        'Unread Notification',
                ]);

        InAppNotification::factory()
            ->read()
            ->create([
                'user_id' =>
                    $customer->id,

                'title' =>
                    'Read Notification',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/notifications?status=unread'
        )
            ->assertOk()

            ->assertJsonCount(
                1,
                'data.notifications'
            )

            ->assertJsonPath(
                'data.notifications.0.id',
                $unread->id
            )

            ->assertJsonPath(
                'data.notifications.0.is_read',
                false
            );
    }

    public function test_unread_count_contains_only_authenticated_users_unread_notifications(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        InAppNotification::factory()
            ->count(
                2
            )
            ->create([
                'user_id' =>
                    $customer->id,
            ]);

        InAppNotification::factory()
            ->read()
            ->create([
                'user_id' =>
                    $customer->id,
            ]);

        InAppNotification::factory()
            ->count(
                4
            )
            ->create([
                'user_id' =>
                    $otherCustomer->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/notifications/unread-count'
        )
            ->assertOk()

            ->assertJsonPath(
                'data.unread_count',
                2
            );
    }

    public function test_customer_can_mark_owned_notification_as_read(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $notification =
            InAppNotification::factory()
                ->create([
                    'user_id' =>
                        $customer->id,
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            "/api/v1/notifications/{$notification->id}/read"
        )
            ->assertOk()

            ->assertJsonPath(
                'data.notification.id',
                $notification->id
            )

            ->assertJsonPath(
                'data.notification.is_read',
                true
            );

        $this->assertNotNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_marking_read_notification_again_is_idempotent(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $originalReadAt =
            now()
                ->subMinutes(
                    5
                )
                ->startOfSecond();

        $notification =
            InAppNotification::factory()
                ->create([
                    'user_id' =>
                        $customer->id,

                    'read_at' =>
                        $originalReadAt,
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            "/api/v1/notifications/{$notification->id}/read"
        )
            ->assertOk()

            ->assertJsonPath(
                'data.notification.is_read',
                true
            );

        $this->assertTrue(
            $notification
                ->fresh()
                ->read_at
                ->equalTo(
                    $originalReadAt
                )
        );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        $notification =
            InAppNotification::factory()
                ->create([
                    'user_id' =>
                        $otherCustomer->id,
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            "/api/v1/notifications/{$notification->id}/read"
        )->assertNotFound();

        $this->assertNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_mark_all_read_updates_only_authenticated_users_notifications(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        InAppNotification::factory()
            ->count(
                3
            )
            ->create([
                'user_id' =>
                    $customer->id,
            ]);

        $otherNotification =
            InAppNotification::factory()
                ->create([
                    'user_id' =>
                        $otherCustomer->id,
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->patchJson(
            '/api/v1/notifications/read-all'
        )
            ->assertOk()

            ->assertJsonPath(
                'data.marked_read_count',
                3
            )

            ->assertJsonPath(
                'data.unread_count',
                0
            );

        $this->assertSame(
            0,
            $customer
                ->inAppNotifications()
                ->unread()
                ->count()
        );

        $this->assertNull(
            $otherNotification
                ->fresh()
                ->read_at
        );
    }

    public function test_notification_query_validation_rejects_invalid_and_unknown_parameters(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/notifications'
            .'?status=deleted'
            .'&per_page=51'
            .'&internal=true'
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'status',
                'per_page',
                'internal',
            ]);
    }
}