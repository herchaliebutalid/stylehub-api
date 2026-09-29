<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearbyShopDiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_nearby_shops_requires_authentication(): void
    {
        $this->getJson(
            $this->nearbyUrl()
        )->assertUnauthorized();
    }

    public function test_shop_owner_cannot_use_customer_nearby_discovery(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            $this->nearbyUrl()
        )->assertForbidden();
    }

    public function test_customer_receives_located_shops_ordered_by_distance(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        [
            ,
            $nearestShop,
        ] =
            $this->visibleLocatedShop([
                'name' =>
                    'Nearest Shop',

                'latitude' =>
                    9.7900000,

                'longitude' =>
                    125.5000000,
            ]);

        [
            ,
            $fartherShop,
        ] =
            $this->visibleLocatedShop([
                'name' =>
                    'Farther Shop',

                'latitude' =>
                    9.8200000,

                'longitude' =>
                    125.5000000,
            ]);

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )->getJson(
                $this->nearbyUrl(
                    latitude:
                        9.7890000,

                    longitude:
                        125.5000000,

                    radiusKm:
                        10
                )
            );

        $response
            ->assertOk()

            ->assertJsonPath(
                'success',
                true
            )

            ->assertJsonCount(
                2,
                'data.shops'
            )

            ->assertJsonPath(
                'data.shops.0.id',
                $nearestShop->id
            )

            ->assertJsonPath(
                'data.shops.1.id',
                $fartherShop->id
            )

            ->assertJsonPath(
                'data.search.radius_km',
                10
            )

            ->assertJsonPath(
                'data.search.results_count',
                2
            );

        $shops =
            $response->json(
                'data.shops'
            );

        $this->assertIsArray(
            $shops
        );

        $this->assertLessThan(
            $shops[1]['distance_km'],
            $shops[0]['distance_km']
        );

        $this->assertTrue(
            $shops[0]
                ['location']
                ['has_location']
        );
    }

    public function test_shop_outside_requested_radius_is_excluded(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->visibleLocatedShop([
            'name' =>
                'Nearby Shop',

            'latitude' =>
                9.7900000,

            'longitude' =>
                125.5000000,
        ]);

        $this->visibleLocatedShop([
            'name' =>
                'Too Far Shop',

            'latitude' =>
                10.0000000,

            'longitude' =>
                125.5000000,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            $this->nearbyUrl(
                radiusKm:
                    5
            )
        )
            ->assertOk()

            ->assertJsonCount(
                1,
                'data.shops'
            )

            ->assertJsonPath(
                'data.shops.0.name',
                'Nearby Shop'
            )

            ->assertJsonMissing([
                'name' =>
                    'Too Far Shop',
            ]);
    }

    public function test_shop_without_coordinates_is_excluded(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $owner = User::factory()
            ->shopOwner()
            ->create();

        Shop::factory()
            ->approved()
            ->create([
                'owner_id' =>
                    $owner->id,

                'name' =>
                    'No Map Pin Shop',

                'latitude' =>
                    null,

                'longitude' =>
                    null,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            $this->nearbyUrl()
        )
            ->assertOk()

            ->assertJsonCount(
                0,
                'data.shops'
            );
    }

    public function test_hidden_located_shops_are_not_returned(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $pendingOwner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()
            ->create([
                'owner_id' =>
                    $pendingOwner->id,

                'name' =>
                    'Pending Nearby Shop',

                'latitude' =>
                    9.7900000,

                'longitude' =>
                    125.5000000,
            ]);

        $suspendedOwner =
            User::factory()
                ->shopOwner()
                ->suspended()
                ->create();

        Shop::factory()
            ->approved()
            ->create([
                'owner_id' =>
                    $suspendedOwner->id,

                'name' =>
                    'Suspended Nearby Shop',

                'latitude' =>
                    9.7910000,

                'longitude' =>
                    125.5000000,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            $this->nearbyUrl()
        )
            ->assertOk()

            ->assertJsonCount(
                0,
                'data.shops'
            )

            ->assertJsonMissing([
                'name' =>
                    'Pending Nearby Shop',
            ])

            ->assertJsonMissing([
                'name' =>
                    'Suspended Nearby Shop',
            ]);
    }

    public function test_nearby_discovery_respects_result_limit(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        foreach (
            [
                [
                    'Shop A',
                    9.7900000,
                ],

                [
                    'Shop B',
                    9.8000000,
                ],

                [
                    'Shop C',
                    9.8100000,
                ],
            ]
            as [
                $name,
                $latitude,
            ]
        ) {
            $this->visibleLocatedShop([
                'name' =>
                    $name,

                'latitude' =>
                    $latitude,

                'longitude' =>
                    125.5000000,
            ]);
        }

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            $this->nearbyUrl(
                limit:
                    2
            )
        )
            ->assertOk()

            ->assertJsonCount(
                2,
                'data.shops'
            )

            ->assertJsonPath(
                'data.search.limit',
                2
            );
    }

    public function test_invalid_nearby_coordinates_and_options_are_rejected(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/customer/shops/nearby'
            .'?latitude=91'
            .'&longitude=181'
            .'&radius_km=0'
            .'&limit=51'
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
                'radius_km',
                'limit',
            ]);
    }

    public function test_unknown_nearby_query_parameter_is_rejected(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            $this->nearbyUrl()
            .'&approval_status=approved'
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'approval_status',
            ]);
    }

    /**
     * Create an approved, active-owner, located shop.
     *
     * @param array<string, mixed> $shopAttributes
     * @return array{0: User, 1: Shop}
     */
    private function visibleLocatedShop(
        array $shopAttributes = []
    ): array {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    ...$shopAttributes,

                    'owner_id' =>
                        $owner->id,

                    'latitude' =>
                        $shopAttributes['latitude']
                        ?? 9.7900000,

                    'longitude' =>
                        $shopAttributes['longitude']
                        ?? 125.5000000,
                ]);

        return [
            $owner,
            $shop,
        ];
    }

    private function nearbyUrl(
        float $latitude = 9.7890000,
        float $longitude = 125.5000000,
        float $radiusKm = 25,
        int $limit = 20
    ): string {
        return sprintf(
            '/api/v1/customer/shops/nearby'
            .'?latitude=%s'
            .'&longitude=%s'
            .'&radius_km=%s'
            .'&limit=%d',

            $latitude,
            $longitude,
            $radiusKm,
            $limit
        );
    }
}