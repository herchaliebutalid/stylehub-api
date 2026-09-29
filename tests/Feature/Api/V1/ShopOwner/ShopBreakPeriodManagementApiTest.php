<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Enums\DayOfWeek;
use App\Models\Shop;
use App\Models\ShopBreakPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopBreakPeriodManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_break_period_routes_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/break-periods'
        )->assertUnauthorized();

        $this->postJson(
            '/api/v1/shop-owner/break-periods',
            $this->validPayload()
        )->assertUnauthorized();
    }

    public function test_customer_cannot_manage_shop_break_periods(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_admin_cannot_manage_shop_break_periods(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_unverified_shop_owner_cannot_manage_break_periods(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->unverifiedPhone()
            ->create();

        Shop::factory()
            ->approved()
            ->create([
                'owner_id' => $owner->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_manage_break_periods(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        Shop::factory()
            ->create([
                'owner_id' => $owner->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_approved_owner_lists_only_own_break_periods_in_weekday_time_order(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $later = ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Tuesday)
            ->between('10:00:00', '10:30:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $earlier = ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('12:00:00', '13:00:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        ShopBreakPeriod::factory()
            ->create([
                'shop_id' => $otherShop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/break-periods'
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.break_periods'
            )
            ->assertJsonPath(
                'data.break_periods.0.id',
                $earlier->id
            )
            ->assertJsonPath(
                'data.break_periods.0.day_label',
                'Monday'
            )
            ->assertJsonPath(
                'data.break_periods.0.starts_at',
                '12:00'
            )
            ->assertJsonPath(
                'data.break_periods.1.id',
                $later->id
            )
            ->assertJsonMissingPath(
                'data.break_periods.0.shop_id'
            );
    }

    public function test_approved_owner_can_create_break_period_for_own_shop(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            [
                'day_of_week' => DayOfWeek::Monday->value,
                'starts_at' => ' 12:00 ',
                'ends_at' => ' 13:00 ',
                'label' => '  Lunch break  ',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.break_period.day_of_week',
                DayOfWeek::Monday->value
            )
            ->assertJsonPath(
                'data.break_period.day_label',
                'Monday'
            )
            ->assertJsonPath(
                'data.break_period.starts_at',
                '12:00'
            )
            ->assertJsonPath(
                'data.break_period.ends_at',
                '13:00'
            )
            ->assertJsonPath(
                'data.break_period.label',
                'Lunch break'
            );

        $this->assertDatabaseHas(
            'shop_break_periods',
            [
                'shop_id' => $shop->id,
                'day_of_week' => DayOfWeek::Monday->value,
                'starts_at' => '12:00:00',
                'ends_at' => '13:00:00',
                'label' => 'Lunch break',
            ]
        );
    }

    public function test_blank_label_is_normalized_to_null(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $payload = $this->validPayload();
        $payload['label'] = '   ';

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            $payload
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.break_period.label',
                null
            );

        $this->assertDatabaseHas(
            'shop_break_periods',
            [
                'shop_id' => $shop->id,
                'label' => null,
            ]
        );
    }

    public function test_break_end_must_be_later_than_start(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            [
                'day_of_week' => DayOfWeek::Monday->value,
                'starts_at' => '13:00',
                'ends_at' => '12:00',
                'label' => null,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ends_at',
            ]);
    }

    public function test_overlapping_break_period_on_same_day_is_rejected(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('12:00:00', '13:00:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            [
                'day_of_week' => DayOfWeek::Monday->value,
                'starts_at' => '12:30',
                'ends_at' => '13:30',
                'label' => 'Overlap attempt',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'starts_at',
            ]);
    }

    public function test_adjacent_break_periods_are_allowed(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('12:00:00', '13:00:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            [
                'day_of_week' => DayOfWeek::Monday->value,
                'starts_at' => '13:00',
                'ends_at' => '13:30',
                'label' => 'Cleaning',
            ]
        )->assertCreated();

        $this->assertSame(
            2,
            $shop->breakPeriods()->count()
        );
    }

    public function test_same_time_on_different_weekday_is_allowed(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('12:00:00', '13:00:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            [
                'day_of_week' => DayOfWeek::Tuesday->value,
                'starts_at' => '12:00',
                'ends_at' => '13:00',
                'label' => 'Lunch',
            ]
        )->assertCreated();
    }

    public function test_owner_can_update_own_break_period(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $breakPeriod = ShopBreakPeriod::factory()
            ->create([
                'shop_id' => $shop->id,
                'label' => 'Old label',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            "/api/v1/shop-owner/break-periods/{$breakPeriod->id}",
            [
                'day_of_week' => DayOfWeek::Friday->value,
                'starts_at' => '14:00',
                'ends_at' => '14:45',
                'label' => ' Staff reset ',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.break_period.id',
                $breakPeriod->id
            )
            ->assertJsonPath(
                'data.break_period.day_label',
                'Friday'
            )
            ->assertJsonPath(
                'data.break_period.label',
                'Staff reset'
            );
    }

    public function test_update_cannot_overlap_an_existing_break_period(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $first = ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('10:00:00', '10:30:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('12:00:00', '13:00:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            "/api/v1/shop-owner/break-periods/{$first->id}",
            [
                'day_of_week' => DayOfWeek::Monday->value,
                'starts_at' => '12:30',
                'ends_at' => '13:30',
                'label' => 'Collision',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'starts_at',
            ]);
    }

    public function test_owner_cannot_update_or_delete_another_shops_break_period(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $foreignBreak = ShopBreakPeriod::factory()
            ->create([
                'shop_id' => $otherShop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            "/api/v1/shop-owner/break-periods/{$foreignBreak->id}",
            $this->validPayload()
        )->assertNotFound();

        $this->actingAs(
            $owner,
            'sanctum'
        )->deleteJson(
            "/api/v1/shop-owner/break-periods/{$foreignBreak->id}"
        )->assertNotFound();

        $this->assertDatabaseHas(
            'shop_break_periods',
            [
                'id' => $foreignBreak->id,
            ]
        );
    }

    public function test_owner_can_delete_own_break_period(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $breakPeriod = ShopBreakPeriod::factory()
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->deleteJson(
            "/api/v1/shop-owner/break-periods/{$breakPeriod->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );

        $this->assertDatabaseMissing(
            'shop_break_periods',
            [
                'id' => $breakPeriod->id,
            ]
        );
    }

    public function test_owner_cannot_inject_shop_ownership_or_unknown_fields(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $payload = $this->validPayload();

        $payload['shop_id'] =
            $otherShop->id;

        $payload['is_active'] =
            false;

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/break-periods',
            $payload
        )->assertUnprocessable();

        $this->assertDatabaseCount(
            'shop_break_periods',
            0
        );
    }

    /**
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
                'owner_id' => $owner->id,
            ]);

        return [
            $owner,
            $shop,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'day_of_week' =>
                DayOfWeek::Monday->value,

            'starts_at' =>
                '12:00',

            'ends_at' =>
                '13:00',

            'label' =>
                'Lunch break',
        ];
    }
}