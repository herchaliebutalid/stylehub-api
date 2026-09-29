<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Enums\AppointmentStatus;
use App\Enums\InAppNotificationType;
use App\Enums\RescheduleRequestStatus;
use App\Models\Appointment;
use App\Models\InAppNotification;
use App\Models\RescheduleRequest;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentNoShowApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' =>
                'Asia/Manila',
        ]);

        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                year: 2026,
                month: 9,
                day: 11,
                hour: 10,
                minute: 0,
                second: 0,
                timezone: 'Asia/Manila'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_mark_no_show_requires_authentication(): void
    {
        $this->patchJson(
            '/api/v1/shop-owner/appointments/1/no-show'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_mark_appointment_no_show(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/shop-owner/appointments/1/no-show'
            )
            ->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_mark_appointment_no_show(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()->create([
            'owner_id' =>
                $owner->id,
        ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                '/api/v1/shop-owner/appointments/1/no-show'
            )
            ->assertForbidden();
    }

    public function test_owner_cannot_mark_another_shops_appointment_no_show(): void
    {
        [$owner] =
            $this->approvedOwnerShopAndService();

        $otherShop =
            Shop::factory()
                ->approved()
                ->create();

        $otherService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $otherShop->id,
                ]);

        $appointment =
            $this->appointment(
                shop:
                    $otherShop,

                service:
                    $otherService,

                date:
                    '2026-09-11',

                startsAt:
                    '09:00:00',

                endsAt:
                    '10:00:00',

                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/no-show"
            )
            ->assertNotFound();

        $appointment->refresh();

        $this->assertSame(
            AppointmentStatus::Confirmed,
            $appointment->status
        );

        $this->assertNull(
            $appointment->no_show_at
        );
    }

    public function test_future_confirmed_appointment_cannot_be_marked_no_show(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-11',

                startsAt:
                    '10:01:00',

                endsAt:
                    '11:01:00',

                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/no-show"
            )
            ->assertConflict();

        $appointment->refresh();

        $this->assertSame(
            AppointmentStatus::Confirmed,
            $appointment->status
        );

        $this->assertNull(
            $appointment->no_show_at
        );
    }

    public function test_owner_can_mark_confirmed_appointment_no_show_at_exact_start_time(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-11',

                startsAt:
                    '10:00:00',

                endsAt:
                    '11:00:00',

                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/no-show"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::NoShow
                    ->value
            )
            ->assertJsonPath(
                'data.appointment.status_label',
                'No-show'
            )
            ->assertJsonPath(
                'data.appointment.no_show_at',
                '2026-09-11T02:00:00.000000Z'
            );

        $updated =
            $appointment->fresh();

        $this->assertSame(
            AppointmentStatus::NoShow,
            $updated->status
        );

        $this->assertNotNull(
            $updated->no_show_at
        );

        $this->assertNull(
            $updated->completed_at
        );

        $this->assertNull(
            $updated->cancelled_at
        );
    }

    public function test_owner_can_mark_confirmed_appointment_no_show_after_start_time(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-11',

                startsAt:
                    '09:00:00',

                endsAt:
                    '10:00:00',

                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/no-show"
            )
            ->assertOk();

        $this->assertSame(
            AppointmentStatus::NoShow,
            $appointment->fresh()->status
        );
    }

    public function test_non_confirmed_appointments_cannot_be_marked_no_show(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        foreach (
            [
                AppointmentStatus::Pending,
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
                AppointmentStatus::Declined,
                AppointmentStatus::NoShow,
            ] as $status
        ) {
            $appointment =
                $this->appointment(
                    shop:
                        $shop,

                    service:
                        $service,

                    date:
                        '2026-09-10',

                    startsAt:
                        '09:00:00',

                    endsAt:
                        '10:00:00',

                    status:
                        $status
                );

            $this->actingAs(
                $owner,
                'sanctum'
            )
                ->patchJson(
                    "/api/v1/shop-owner/appointments/{$appointment->id}/no-show"
                )
                ->assertConflict();
        }
    }

    public function test_marking_no_show_withdraws_pending_reschedule_request(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-11',

                startsAt:
                    '09:00:00',

                endsAt:
                    '10:00:00',

                status:
                    AppointmentStatus::Confirmed
            );

        $reschedule =
            RescheduleRequest::factory()
                ->create([
                    'appointment_id' =>
                        $appointment->id,

                    'status' =>
                        RescheduleRequestStatus::Pending,
                ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/no-show"
            )
            ->assertOk();

        $this->assertSame(
            RescheduleRequestStatus::Withdrawn,
            $reschedule->fresh()->status
        );
    }

    public function test_marking_no_show_notifies_customer_once(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop:
                    $shop,

                service:
                    $service,

                date:
                    '2026-09-11',

                startsAt:
                    '09:00:00',

                endsAt:
                    '10:00:00',

                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/no-show"
            )
            ->assertOk();

        $notification =
            InAppNotification::query()
                ->where(
                    'appointment_id',
                    $appointment->id
                )
                ->where(
                    'user_id',
                    $appointment->customer_id
                )
                ->where(
                    'type',
                    InAppNotificationType::AppointmentNoShow
                        ->value
                )
                ->firstOrFail();

        $this->assertStringContainsString(
            'no-show',
            $notification->message
        );

        $this->assertDatabaseCount(
            'in_app_notifications',
            1
        );
    }

    /**
     * @return array{0: User, 1: Shop, 2: Service}
     */
    private function approvedOwnerShopAndService(): array
    {
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
                ]);

        return [
            $owner,
            $shop,
            $service,
        ];
    }

    private function appointment(
        Shop $shop,
        Service $service,
        string $date,
        string $startsAt,
        string $endsAt,
        AppointmentStatus $status
    ): Appointment {
        return Appointment::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $service->id,

                'appointment_date' =>
                    $date,

                'starts_at' =>
                    $startsAt,

                'ends_at' =>
                    $endsAt,

                'service_name' =>
                    $service->name,

                'price_snapshot' =>
                    $service->price,

                'duration_minutes_snapshot' =>
                    $service->duration_minutes,

                'status' =>
                    $status,
            ]);
    }
}