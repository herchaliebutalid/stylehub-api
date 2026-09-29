<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopLocationManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/location'
        )->assertUnauthorized();

        $this->putJson(
            '/api/v1/shop-owner/location',
            $this->validLocationPayload()
        )->assertUnauthorized();
    }

    public function test_customer_cannot_manage_shop_location(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/location'
        )->assertForbidden();
    }

    public function test_admin_cannot_manage_shop_location(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/location'
        )->assertForbidden();
    }

    public function test_unverified_shop_owner_cannot_manage_location(): void
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
        )->putJson(
            '/api/v1/shop-owner/location',
            $this->validLocationPayload()
        )->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_manage_location(): void
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
            '/api/v1/shop-owner/location',
            $this->validLocationPayload()
        )->assertForbidden();
    }

    public function test_approved_owner_can_view_own_location(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop([
                'latitude' => 9.7891234,
                'longitude' => 125.4956789,
            ]);

        $response = $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/location'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.location.shop_id',
                $shop->id
            )
            ->assertJsonPath(
                'data.location.shop_name',
                $shop->name
            )
            ->assertJsonPath(
                'data.location.address',
                $shop->address
            )
            ->assertJsonPath(
                'data.location.city',
                $shop->city
            )
            ->assertJsonPath(
                'data.location.province',
                $shop->province
            )
            ->assertJsonPath(
                'data.location.latitude',
                9.7891234
            )
            ->assertJsonPath(
                'data.location.longitude',
                125.4956789
            )
            ->assertJsonPath(
                'data.location.has_location',
                true
            );
    }

    public function test_approved_owner_can_view_unset_location(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/location'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.location.shop_id',
                $shop->id
            )
            ->assertJsonPath(
                'data.location.latitude',
                null
            )
            ->assertJsonPath(
                'data.location.longitude',
                null
            )
            ->assertJsonPath(
                'data.location.has_location',
                false
            );
    }

    public function test_approved_owner_can_update_own_location(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $response = $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/location',
            [
                'latitude' => 9.7891234,
                'longitude' => 125.4956789,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'message',
                'Shop location updated successfully.'
            )
            ->assertJsonPath(
                'data.location.shop_id',
                $shop->id
            )
            ->assertJsonPath(
                'data.location.latitude',
                9.7891234
            )
            ->assertJsonPath(
                'data.location.longitude',
                125.4956789
            )
            ->assertJsonPath(
                'data.location.has_location',
                true
            );

        $freshShop = $shop->fresh();

        $this->assertNotNull(
            $freshShop
        );

        $this->assertEqualsWithDelta(
            9.7891234,
            $freshShop->latitude,
            0.0000001
        );

        $this->assertEqualsWithDelta(
            125.4956789,
            $freshShop->longitude,
            0.0000001
        );
    }

    public function test_location_update_never_modifies_another_shop(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create([
                'latitude' => 10.3157000,
                'longitude' => 123.8854000,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/location',
            [
                'latitude' => 9.7891234,
                'longitude' => 125.4956789,
            ]
        )->assertOk();

        $this->assertEqualsWithDelta(
            9.7891234,
            $shop->fresh()->latitude,
            0.0000001
        );

        $this->assertEqualsWithDelta(
            125.4956789,
            $shop->fresh()->longitude,
            0.0000001
        );

        $this->assertEqualsWithDelta(
            10.3157000,
            $otherShop->fresh()->latitude,
            0.0000001
        );

        $this->assertEqualsWithDelta(
            123.8854000,
            $otherShop->fresh()->longitude,
            0.0000001
        );
    }

    public function test_owner_cannot_inject_shop_id_into_location_update(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create([
                'latitude' => 10.3157000,
                'longitude' => 123.8854000,
            ]);

        $payload = $this->validLocationPayload();

        $payload['shop_id'] =
            $otherShop->id;

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/location',
            $payload
        )->assertUnprocessable();

        $this->assertNull(
            $shop->fresh()->latitude
        );

        $this->assertNull(
            $shop->fresh()->longitude
        );

        $this->assertEqualsWithDelta(
            10.3157000,
            $otherShop->fresh()->latitude,
            0.0000001
        );

        $this->assertEqualsWithDelta(
            123.8854000,
            $otherShop->fresh()->longitude,
            0.0000001
        );
    }

    public function test_location_requires_complete_coordinate_pair(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/location',
            [
                'latitude' => 9.7891234,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'longitude',
            ]);

        $this->assertNull(
            $shop->fresh()->latitude
        );

        $this->assertNull(
            $shop->fresh()->longitude
        );
    }

    public function test_latitude_outside_world_bounds_is_rejected(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        foreach (
            [-90.0000001, 90.0000001]
            as $invalidLatitude
        ) {
            $this->actingAs(
                $owner,
                'sanctum'
            )->putJson(
                '/api/v1/shop-owner/location',
                [
                    'latitude' =>
                        $invalidLatitude,

                    'longitude' =>
                        125.4956789,
                ]
            )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'latitude',
                ]);
        }

        $this->assertNull(
            $shop->fresh()->latitude
        );
    }

    public function test_longitude_outside_world_bounds_is_rejected(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        foreach (
            [-180.0000001, 180.0000001]
            as $invalidLongitude
        ) {
            $this->actingAs(
                $owner,
                'sanctum'
            )->putJson(
                '/api/v1/shop-owner/location',
                [
                    'latitude' =>
                        9.7891234,

                    'longitude' =>
                        $invalidLongitude,
                ]
            )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'longitude',
                ]);
        }

        $this->assertNull(
            $shop->fresh()->longitude
        );
    }

    /**
     * Create an approved owner and their shop.
     *
     * @param array<string, mixed> $shopAttributes
     * @return array{0: User, 1: Shop}
     */
    private function approvedOwnerAndShop(
        array $shopAttributes = []
    ): array {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $shop = Shop::factory()
            ->approved()
            ->create([
                ...$shopAttributes,

                'owner_id' =>
                    $owner->id,
            ]);

        return [
            $owner,
            $shop,
        ];
    }

    /**
     * Build a valid map location payload.
     *
     * @return array<string, float>
     */
    private function validLocationPayload(): array
    {
        return [
            'latitude' =>
                9.7891234,

            'longitude' =>
                125.4956789,
        ];
    }
}