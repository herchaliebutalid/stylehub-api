<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            '2026-08-20 08:00:00'
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_appointment_management_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/appointments'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_use_shop_owner_appointment_routes(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/appointments'
        )->assertForbidden();
    }

    public function test_admin_cannot_use_shop_owner_appointment_routes(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/appointments'
        )->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_manage_appointments(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        Shop::factory()->create([
            'owner_id' =>
                $owner->id,
        ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/appointments'
        )->assertForbidden();
    }

    public function test_owner_lists_only_own_shop_appointments(): void
    {
        [$owner, $shop, $service] =
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

        $this->appointment(
            shop: $shop,
            service: $service
        );

        $this->appointment(
            shop: $shop,
            service: $service,
            startsAt: '12:00:00',
            endsAt: '13:00:00'
        );

        $this->appointment(
            shop: $otherShop,
            service: $otherService
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/appointments'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.appointments'
            );
    }

    public function test_owner_cannot_view_another_shops_appointment(): void
    {
        [$owner] =
            $this->approvedOwnerShopAndService();

        $otherShop =
            Shop::factory()
                ->approved()
                ->create();

        $otherService =
            Service::factory()->create([
                'shop_id' =>
                    $otherShop->id,
            ]);

        $appointment =
            $this->appointment(
                shop: $otherShop,
                service: $otherService
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}"
            )
            ->assertNotFound();
    }

    public function test_owner_can_confirm_pending_future_appointment(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/confirm"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Confirmed
                    ->value
            );

        $this->assertSame(
            AppointmentStatus::Confirmed,
            $appointment->fresh()->status
        );
    }

    public function test_owner_cannot_confirm_another_shops_appointment(): void
    {
        [$owner] =
            $this->approvedOwnerShopAndService();

        $otherShop =
            Shop::factory()
                ->approved()
                ->create();

        $otherService =
            Service::factory()->create([
                'shop_id' =>
                    $otherShop->id,
            ]);

        $appointment =
            $this->appointment(
                shop: $otherShop,
                service: $otherService
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/confirm"
            )
            ->assertNotFound();

        $this->assertSame(
            AppointmentStatus::Pending,
            $appointment->fresh()->status
        );
    }

    public function test_confirmed_appointment_cannot_be_confirmed_again(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service,
                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/confirm"
            )
            ->assertConflict();
    }

    public function test_owner_can_decline_pending_appointment_with_reason(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/decline",
                [
                    'reason' =>
                        'Requested time is unavailable.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Declined
                    ->value
            );

        $updated =
            $appointment->fresh();

        $this->assertSame(
            AppointmentStatus::Declined,
            $updated->status
        );

        $this->assertSame(
            'Requested time is unavailable.',
            $updated->cancellation_reason
        );

        $this->assertSame(
            $owner->id,
            $updated->cancelled_by
        );

        $this->assertNotNull(
            $updated->cancelled_at
        );
    }

    public function test_decline_requires_meaningful_reason(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/decline",
                [
                    'reason' => 'No',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);

        $this->assertSame(
            AppointmentStatus::Pending,
            $appointment->fresh()->status
        );
    }

    public function test_confirmed_appointment_cannot_be_declined(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service,
                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/decline",
                [
                    'reason' =>
                        'Cannot accept appointment.',
                ]
            )
            ->assertConflict();
    }

    public function test_owner_can_cancel_confirmed_future_appointment(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service,
                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/cancel",
                [
                    'reason' =>
                        'Shop will be closed unexpectedly.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Cancelled
                    ->value
            );

        $updated =
            $appointment->fresh();

        $this->assertSame(
            AppointmentStatus::Cancelled,
            $updated->status
        );

        $this->assertSame(
            $owner->id,
            $updated->cancelled_by
        );

        $this->assertNotNull(
            $updated->cancelled_at
        );
    }

    public function test_pending_appointment_cannot_use_shop_cancel_action(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/cancel",
                [
                    'reason' =>
                        'Shop will be unavailable.',
                ]
            )
            ->assertConflict();

        $this->assertSame(
            AppointmentStatus::Pending,
            $appointment->fresh()->status
        );
    }

    public function test_owner_cannot_cancel_confirmed_appointment_after_it_started(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service,
                date: '2026-08-20',
                startsAt: '07:00:00',
                endsAt: '08:00:00',
                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/cancel",
                [
                    'reason' =>
                        'Attempted late cancellation.',
                ]
            )
            ->assertConflict();

        $this->assertSame(
            AppointmentStatus::Confirmed,
            $appointment->fresh()->status
        );
    }

    public function test_owner_can_complete_confirmed_finished_appointment(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service,
                date: '2026-08-19',
                startsAt: '10:00:00',
                endsAt: '11:00:00',
                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/complete"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.appointment.status',
                AppointmentStatus::Completed
                    ->value
            );

        $updated =
            $appointment->fresh();

        $this->assertSame(
            AppointmentStatus::Completed,
            $updated->status
        );

        $this->assertNotNull(
            $updated->completed_at
        );
    }

    public function test_future_appointment_cannot_be_completed_early(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service,
                status:
                    AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/complete"
            )
            ->assertConflict();

        $this->assertSame(
            AppointmentStatus::Confirmed,
            $appointment->fresh()->status
        );
    }

    public function test_pending_appointment_cannot_be_completed(): void
    {
        [$owner, $shop, $service] =
            $this->approvedOwnerShopAndService();

        $appointment =
            $this->appointment(
                shop: $shop,
                service: $service
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/shop-owner/appointments/{$appointment->id}/complete"
            )
            ->assertConflict();

        $this->assertSame(
            AppointmentStatus::Pending,
            $appointment->fresh()->status
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

                    'name' =>
                        'Premium Haircut',

                    'price' =>
                        '500.00',

                    'duration_minutes' =>
                        60,

                    'is_active' =>
                        true,
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
        string $date = '2026-08-24',
        string $startsAt = '10:00:00',
        string $endsAt = '11:00:00',
        AppointmentStatus $status =
            AppointmentStatus::Pending
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
                    $service
                        ->duration_minutes,

                'status' =>
                    $status,
            ]);
    }
}