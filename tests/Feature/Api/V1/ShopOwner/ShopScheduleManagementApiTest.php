<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Enums\DayOfWeek;
use App\Enums\ShopAvailabilityStatus;
use App\Models\Shop;
use App\Models\ShopSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopScheduleManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/schedule'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_manage_shop_schedule(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/schedule'
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_use_shop_owner_schedule_routes(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/schedule'
            )
            ->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_manage_schedule(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        Shop::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $this->validSchedulePayload()
        )->assertForbidden();
    }

    public function test_approved_owner_can_save_complete_weekly_schedule(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $response = $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $this->validSchedulePayload()
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                7,
                'data.schedule'
            )
            ->assertJsonPath(
                'data.schedule.0.day_of_week',
                DayOfWeek::Monday->value
            )
            ->assertJsonPath(
                'data.schedule.6.day_of_week',
                DayOfWeek::Sunday->value
            )
            ->assertJsonPath(
                'data.schedule.6.is_open',
                false
            );

        $this->assertSame(
            7,
            ShopSchedule::query()
                ->where(
                    'shop_id',
                    $shop->id
                )
                ->count()
        );
    }

    public function test_repeated_schedule_update_does_not_create_duplicates(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validSchedulePayload();

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $payload
        )->assertOk();

        $payload['schedule'][0]['opens_at'] =
            '08:00';

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $payload
        )->assertOk();

        $this->assertSame(
            7,
            ShopSchedule::query()
                ->where(
                    'shop_id',
                    $shop->id
                )
                ->count()
        );

        $this->assertDatabaseHas(
            'shop_schedules',
            [
                'shop_id' => $shop->id,
                'day_of_week' =>
                    DayOfWeek::Monday->value,
                'opens_at' => '08:00:00',
            ]
        );
    }

    public function test_schedule_update_does_not_modify_another_shop(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        foreach (
            DayOfWeek::ordered()
            as $day
        ) {
            ShopSchedule::factory()
                ->forDay($day)
                ->openBetween(
                    '10:00:00',
                    '20:00:00'
                )
                ->create([
                    'shop_id' =>
                        $otherShop->id,
                ]);
        }

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $this->validSchedulePayload()
        )->assertOk();

        $this->assertSame(
            7,
            ShopSchedule::query()
                ->where(
                    'shop_id',
                    $shop->id
                )
                ->count()
        );

        $this->assertSame(
            7,
            ShopSchedule::query()
                ->where(
                    'shop_id',
                    $otherShop->id
                )
                ->count()
        );

        $this->assertDatabaseHas(
            'shop_schedules',
            [
                'shop_id' =>
                    $otherShop->id,

                'day_of_week' =>
                    DayOfWeek::Monday->value,

                'opens_at' =>
                    '10:00:00',

                'closes_at' =>
                    '20:00:00',
            ]
        );
    }

    public function test_duplicate_weekday_is_rejected(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validSchedulePayload();

        $payload['schedule'][6]['day_of_week'] =
            DayOfWeek::Monday->value;

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'schedule.6.day_of_week',
            ]);

        $this->assertDatabaseCount(
            'shop_schedules',
            0
        );
    }

    public function test_incomplete_weekly_schedule_is_rejected(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validSchedulePayload();

        array_pop(
            $payload['schedule']
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'schedule',
            ]);

        $this->assertDatabaseCount(
            'shop_schedules',
            0
        );
    }

    public function test_closing_time_must_be_after_opening_time(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validSchedulePayload();

        $payload['schedule'][0]['opens_at'] =
            '18:00';

        $payload['schedule'][0]['closes_at'] =
            '09:00';

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'schedule.0.closes_at',
            ]);

        $this->assertDatabaseCount(
            'shop_schedules',
            0
        );
    }

    public function test_closed_day_cannot_have_hidden_operating_hours(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validSchedulePayload();

        $payload['schedule'][6]['opens_at'] =
            '09:00';

        $payload['schedule'][6]['closes_at'] =
            '17:00';

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'schedule.6.opens_at',
                'schedule.6.closes_at',
            ]);

        $this->assertDatabaseCount(
            'shop_schedules',
            0
        );
    }

    public function test_owner_cannot_inject_shop_id_into_schedule(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $payload =
            $this->validSchedulePayload();

        $payload['schedule'][0]['shop_id'] =
            $otherShop->id;

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/schedule',
            $payload
        )->assertUnprocessable();

        $this->assertDatabaseCount(
            'shop_schedules',
            0
        );
    }

    public function test_approved_owner_can_update_shop_availability(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $response = $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            '/api/v1/shop-owner/availability',
            [
                'status' =>
                    ShopAvailabilityStatus::Open
                        ->value,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.availability.status',
                ShopAvailabilityStatus::Open
                    ->value
            );

        $this->assertSame(
            ShopAvailabilityStatus::Open,
            $shop->fresh()
                ->availability_status
        );
    }

    public function test_invalid_availability_status_is_rejected(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            '/api/v1/shop-owner/availability',
            [
                'status' => 'super_open',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->assertSame(
            ShopAvailabilityStatus::Closed,
            $shop->fresh()
                ->availability_status
        );
    }

    public function test_pending_owner_cannot_update_availability(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $shop = Shop::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->patchJson(
            '/api/v1/shop-owner/availability',
            [
                'status' =>
                    ShopAvailabilityStatus::Open
                        ->value,
            ]
        )->assertForbidden();

        $this->assertSame(
            ShopAvailabilityStatus::Closed,
            $shop->fresh()
                ->availability_status
        );
    }

    /**
     * Create an approved owner and shop.
     *
     * @return array{0: User, 1: Shop}
     */
    private function approvedOwnerAndShop(): array
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $shop = Shop::factory()
            ->approved()
            ->create([
                'owner_id' =>
                    $owner->id,
            ]);

        return [
            $owner,
            $shop,
        ];
    }

    /**
     * Build a valid complete weekly schedule.
     *
     * @return array<string, mixed>
     */
    private function validSchedulePayload(): array
    {
        return [
            'schedule' => [
                [
                    'day_of_week' =>
                        DayOfWeek::Monday->value,

                    'is_open' => true,
                    'opens_at' => '09:00',
                    'closes_at' => '18:00',
                ],

                [
                    'day_of_week' =>
                        DayOfWeek::Tuesday->value,

                    'is_open' => true,
                    'opens_at' => '09:00',
                    'closes_at' => '18:00',
                ],

                [
                    'day_of_week' =>
                        DayOfWeek::Wednesday->value,

                    'is_open' => true,
                    'opens_at' => '09:00',
                    'closes_at' => '18:00',
                ],

                [
                    'day_of_week' =>
                        DayOfWeek::Thursday->value,

                    'is_open' => true,
                    'opens_at' => '09:00',
                    'closes_at' => '18:00',
                ],

                [
                    'day_of_week' =>
                        DayOfWeek::Friday->value,

                    'is_open' => true,
                    'opens_at' => '09:00',
                    'closes_at' => '18:00',
                ],

                [
                    'day_of_week' =>
                        DayOfWeek::Saturday->value,

                    'is_open' => true,
                    'opens_at' => '09:00',
                    'closes_at' => '17:00',
                ],

                [
                    'day_of_week' =>
                        DayOfWeek::Sunday->value,

                    'is_open' => false,
                    'opens_at' => null,
                    'closes_at' => null,
                ],
            ],
        ];
    }
}