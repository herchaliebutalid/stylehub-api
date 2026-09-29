<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Models\Shop;
use App\Models\ShopBlockedDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopBlockedDateManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_date_routes_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/blocked-dates'
        )->assertUnauthorized();

        $this->postJson(
            '/api/v1/shop-owner/blocked-dates',
            $this->validPayload()
        )->assertUnauthorized();
    }

    public function test_customer_cannot_manage_shop_blocked_dates(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/blocked-dates'
        )->assertForbidden();
    }

    public function test_admin_cannot_manage_shop_blocked_dates(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/blocked-dates',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_unverified_shop_owner_cannot_manage_blocked_dates(): void
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
            '/api/v1/shop-owner/blocked-dates',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_manage_blocked_dates(): void
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
            '/api/v1/shop-owner/blocked-dates',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_approved_owner_lists_only_own_blocked_dates_in_date_order(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $later = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => $this->futureDate(20),
                'reason' => 'Later closure',
            ]);

        $earlier = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => $this->futureDate(10),
                'reason' => 'Earlier closure',
            ]);

        ShopBlockedDate::factory()
            ->create([
                'shop_id' => $otherShop->id,
                'blocked_date' => $this->futureDate(5),
                'reason' => 'Other tenant closure',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/blocked-dates'
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                2,
                'data.blocked_dates'
            )
            ->assertJsonPath(
                'data.blocked_dates.0.id',
                $earlier->id
            )
            ->assertJsonPath(
                'data.blocked_dates.1.id',
                $later->id
            )
            ->assertJsonMissingPath(
                'data.blocked_dates.0.shop_id'
            );
    }

    public function test_approved_owner_can_create_blocked_date_for_own_shop(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $blockedDate = $this->futureDate(
            14
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/blocked-dates',
            [
                'blocked_date' => $blockedDate,
                'reason' => '  Shop maintenance  ',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.blocked_date.blocked_date',
                $blockedDate
            )
            ->assertJsonPath(
                'data.blocked_date.reason',
                'Shop maintenance'
            );

        $this->assertDatabaseHas(
            'shop_blocked_dates',
            [
                'shop_id' => $shop->id,
                'blocked_date' => $blockedDate,
                'reason' => 'Shop maintenance',
            ]
        );
    }

    public function test_blank_reason_is_normalized_to_null(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $blockedDate = $this->futureDate(
            7
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/blocked-dates',
            [
                'blocked_date' => $blockedDate,
                'reason' => '   ',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.blocked_date.reason',
                null
            );

        $this->assertDatabaseHas(
            'shop_blocked_dates',
            [
                'shop_id' => $shop->id,
                'blocked_date' => $blockedDate,
                'reason' => null,
            ]
        );
    }

    public function test_duplicate_date_for_same_shop_is_rejected(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $blockedDate = $this->futureDate(
            12
        );

        ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => $blockedDate,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/blocked-dates',
            [
                'blocked_date' => $blockedDate,
                'reason' => 'Duplicate attempt',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'blocked_date',
            ]);

        $this->assertSame(
            1,
            $shop
                ->blockedDates()
                ->whereDate(
                    'blocked_date',
                    $blockedDate
                )
                ->count()
        );
    }

    public function test_past_blocked_date_is_rejected(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/blocked-dates',
            [
                'blocked_date' => now()
                    ->subDay()
                    ->toDateString(),

                'reason' => 'Past closure',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'blocked_date',
            ]);
    }

    public function test_owner_can_update_own_blocked_date(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $blockedDate = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => $this->futureDate(10),
                'reason' => 'Old reason',
            ]);

        $newDate = $this->futureDate(
            15
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            "/api/v1/shop-owner/blocked-dates/{$blockedDate->id}",
            [
                'blocked_date' => $newDate,
                'reason' => '  Staff event  ',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.blocked_date.id',
                $blockedDate->id
            )
            ->assertJsonPath(
                'data.blocked_date.blocked_date',
                $newDate
            )
            ->assertJsonPath(
                'data.blocked_date.reason',
                'Staff event'
            );

        $this->assertDatabaseHas(
            'shop_blocked_dates',
            [
                'id' => $blockedDate->id,
                'shop_id' => $shop->id,
                'blocked_date' => $newDate,
                'reason' => 'Staff event',
            ]
        );
    }

    public function test_update_cannot_move_blocked_date_onto_existing_date(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $first = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => $this->futureDate(10),
            ]);

        $secondDate = $this->futureDate(
            20
        );

        ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => $secondDate,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            "/api/v1/shop-owner/blocked-dates/{$first->id}",
            [
                'blocked_date' => $secondDate,
                'reason' => 'Collision',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'blocked_date',
            ]);

        $this->assertSame(
            $this->futureDate(10),
            $first
                ->fresh()
                ->blocked_date
                ->format('Y-m-d')
        );
    }

    public function test_owner_cannot_update_or_delete_another_shops_blocked_date(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $foreignBlockedDate = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $otherShop->id,
                'blocked_date' => $this->futureDate(9),
                'reason' => 'Protected closure',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            "/api/v1/shop-owner/blocked-dates/{$foreignBlockedDate->id}",
            $this->validPayload()
        )->assertNotFound();

        $this->actingAs(
            $owner,
            'sanctum'
        )->deleteJson(
            "/api/v1/shop-owner/blocked-dates/{$foreignBlockedDate->id}"
        )->assertNotFound();

        $this->assertDatabaseHas(
            'shop_blocked_dates',
            [
                'id' => $foreignBlockedDate->id,
                'shop_id' => $otherShop->id,
                'reason' => 'Protected closure',
            ]
        );
    }

    public function test_owner_can_delete_own_blocked_date_including_past_record(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $blockedDate = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,

                'blocked_date' => now()
                    ->subDay()
                    ->toDateString(),
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->deleteJson(
            "/api/v1/shop-owner/blocked-dates/{$blockedDate->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );

        $this->assertDatabaseMissing(
            'shop_blocked_dates',
            [
                'id' => $blockedDate->id,
            ]
        );
    }

    public function test_owner_cannot_inject_shop_ownership_or_unknown_fields(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $payload = $this->validPayload();

        $payload['shop_id'] =
            $otherShop->id;

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/blocked-dates',
            $payload
        )->assertUnprocessable();

        $this->assertSame(
            0,
            $shop
                ->blockedDates()
                ->count()
        );

        $this->assertSame(
            0,
            $otherShop
                ->blockedDates()
                ->count()
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
            'blocked_date' =>
                $this->futureDate(30),

            'reason' =>
                'Holiday closure',
        ];
    }

    private function futureDate(
        int $days
    ): string {
        return now()
            ->addDays($days)
            ->toDateString();
    }
}