<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\ShopAvailabilityStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerShopLocationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_shop_list_exposes_safe_map_location(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [, $shop] = $this->visibleShop([
            'name' => 'Located StyleHub Shop',
            'latitude' => 9.7891234,
            'longitude' => 125.4956789,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/customer/shops'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.shops.0.location.latitude',
                9.7891234
            )
            ->assertJsonPath(
                'data.shops.0.location.longitude',
                125.4956789
            )
            ->assertJsonPath(
                'data.shops.0.location.has_location',
                true
            );
    }

    public function test_customer_shop_detail_exposes_safe_map_location(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [, $shop] = $this->visibleShop([
            'latitude' => 10.3157000,
            'longitude' => 123.8854000,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            "/api/v1/customer/shops/{$shop->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.location.latitude',
                10.3157
            )
            ->assertJsonPath(
                'data.shop.location.longitude',
                123.8854
            )
            ->assertJsonPath(
                'data.shop.location.has_location',
                true
            );
    }

    public function test_existing_shop_without_coordinates_returns_unset_location(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [, $shop] = $this->visibleShop();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            "/api/v1/customer/shops/{$shop->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.location.latitude',
                null
            )
            ->assertJsonPath(
                'data.shop.location.longitude',
                null
            )
            ->assertJsonPath(
                'data.shop.location.has_location',
                false
            );
    }

    public function test_pending_located_shop_remains_hidden_from_customer(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $owner = User::factory()
            ->shopOwner()
            ->create();

        $shop = Shop::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Pending Located Shop',
            'latitude' => 9.7891234,
            'longitude' => 125.4956789,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/customer/shops'
        )
            ->assertOk()
            ->assertJsonMissing([
                'name' => 'Pending Located Shop',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            "/api/v1/customer/shops/{$shop->id}"
        )->assertNotFound();
    }

    public function test_located_shop_with_suspended_owner_remains_hidden_from_customer(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $owner = User::factory()
            ->shopOwner()
            ->suspended()
            ->create();

        $shop = Shop::factory()
            ->approved()
            ->create([
                'owner_id' => $owner->id,
                'name' => 'Suspended Owner Located Shop',
                'latitude' => 9.7891234,
                'longitude' => 125.4956789,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/customer/shops'
        )
            ->assertOk()
            ->assertJsonMissing([
                'name' => 'Suspended Owner Located Shop',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            "/api/v1/customer/shops/{$shop->id}"
        )->assertNotFound();
    }

    public function test_customer_location_response_does_not_expose_internal_shop_fields(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [, $shop] = $this->visibleShop([
            'latitude' => 9.7891234,
            'longitude' => 125.4956789,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            "/api/v1/customer/shops/{$shop->id}"
        )
            ->assertOk()
            ->assertJsonMissingPath(
                'data.shop.owner_id'
            )
            ->assertJsonMissingPath(
                'data.shop.approval_status'
            )
            ->assertJsonMissingPath(
                'data.shop.rejection_reason'
            )
            ->assertJsonMissingPath(
                'data.shop.reviewed_by'
            )
            ->assertJsonMissingPath(
                'data.shop.reviewed_at'
            );
    }

    /**
     * Create an approved customer-visible shop.
     *
     * @param array<string, mixed> $shopAttributes
     * @return array{0: User, 1: Shop}
     */
    private function visibleShop(
        array $shopAttributes = []
    ): array {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $shop = Shop::factory()
            ->approved()
            ->create([
                ...$shopAttributes,

                'owner_id' => $owner->id,

                'availability_status' =>
                    $shopAttributes['availability_status']
                    ?? ShopAvailabilityStatus::Open,

                'name' =>
                    $shopAttributes['name']
                    ?? 'StyleHub Shop',

                'city' =>
                    $shopAttributes['city']
                    ?? 'Surigao City',

                'province' =>
                    $shopAttributes['province']
                    ?? 'Surigao del Norte',
            ]);

        return [
            $owner,
            $shop,
        ];
    }
}