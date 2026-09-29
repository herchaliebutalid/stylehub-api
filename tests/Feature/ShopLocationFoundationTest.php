<?php

namespace Tests\Feature;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopLocationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_location_columns_exist_and_existing_shops_remain_valid(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'shops',
                [
                    'latitude',
                    'longitude',
                ]
            )
        );

        $shop = Shop::factory()->create();

        $this->assertNull(
            $shop->latitude
        );

        $this->assertNull(
            $shop->longitude
        );

        $this->assertFalse(
            $shop->hasLocation()
        );
    }

    public function test_shop_can_store_precise_location_coordinates(): void
    {
        $shop = Shop::factory()->create([
            'latitude' =>
                9.7891234,

            'longitude' =>
                125.4956789,
        ])->fresh();

        $this->assertNotNull(
            $shop
        );

        $this->assertIsFloat(
            $shop->latitude
        );

        $this->assertIsFloat(
            $shop->longitude
        );

        $this->assertEqualsWithDelta(
            9.7891234,
            $shop->latitude,
            0.0000001
        );

        $this->assertEqualsWithDelta(
            125.4956789,
            $shop->longitude,
            0.0000001
        );

        $this->assertTrue(
            $shop->hasLocation()
        );
    }

    public function test_located_scope_requires_a_complete_coordinate_pair(): void
    {
        $locatedShop = Shop::factory()->create([
            'latitude' =>
                9.7891234,

            'longitude' =>
                125.4956789,
        ]);

        Shop::factory()->create([
            'latitude' =>
                9.7891234,

            'longitude' =>
                null,
        ]);

        Shop::factory()->create([
            'latitude' =>
                null,

            'longitude' =>
                125.4956789,
        ]);

        Shop::factory()->create();

        $locatedIds = Shop::query()
            ->located()
            ->pluck(
                'id'
            )
            ->all();

        $this->assertSame(
            [
                $locatedShop->id,
            ],
            $locatedIds
        );
    }

    public function test_location_coordinates_cannot_be_mass_assigned(): void
    {
        $shop = new Shop();

        $shop->fill([
            'name' =>
                'Secure Location Shop',

            'latitude' =>
                9.7891234,

            'longitude' =>
                125.4956789,
        ]);

        $this->assertSame(
            'Secure Location Shop',
            $shop->name
        );

        $this->assertNull(
            $shop->latitude
        );

        $this->assertNull(
            $shop->longitude
        );
    }
}