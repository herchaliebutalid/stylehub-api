<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopBlockedDate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopBlockedDateFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_date_schema_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'shop_blocked_dates',
                [
                    'id',
                    'shop_id',
                    'blocked_date',
                    'reason',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_blocked_date_belongs_to_shop_and_shop_has_blocked_dates(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $blockedDate = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => '2026-12-25',
                'reason' => 'Christmas closure',
            ]);

        $this->assertSame(
            $shop->id,
            $blockedDate->shop->id
        );

        $this->assertTrue(
            $shop
                ->blockedDates()
                ->whereKey(
                    $blockedDate->id
                )
                ->exists()
        );
    }

    public function test_blocked_date_is_cast_as_calendar_date(): void
    {
        $blockedDate = ShopBlockedDate::factory()
            ->create([
                'blocked_date' => '2026-12-25',
            ]);

        $this->assertSame(
            '2026-12-25',
            $blockedDate
                ->blocked_date
                ->format('Y-m-d')
        );
    }

    public function test_blocked_dates_are_ordered_chronologically(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $later = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => '2026-12-31',
            ]);

        $earlier = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => '2026-11-01',
            ]);

        $orderedIds = $shop
            ->blockedDates()
            ->ordered()
            ->pluck('id')
            ->all();

        $this->assertSame(
            [
                $earlier->id,
                $later->id,
            ],
            $orderedIds
        );
    }

    public function test_shop_id_cannot_be_mass_assigned_to_blocked_date(): void
    {
        $blockedDate = new ShopBlockedDate();

        $blockedDate->fill([
            'shop_id' => 999,
            'blocked_date' => '2026-12-25',
            'reason' => 'Holiday closure',
        ]);

        $this->assertNull(
            $blockedDate->shop_id
        );

        $this->assertSame(
            'Holiday closure',
            $blockedDate->reason
        );
    }

    public function test_shop_cannot_have_duplicate_blocked_date(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => '2026-12-25',
            ]);

        $this->expectException(
            QueryException::class
        );

        ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
                'blocked_date' => '2026-12-25',
            ]);
    }

    public function test_different_shops_may_block_the_same_date(): void
    {
        $firstShop = Shop::factory()
            ->approved()
            ->create();

        $secondShop = Shop::factory()
            ->approved()
            ->create();

        ShopBlockedDate::factory()
            ->create([
                'shop_id' => $firstShop->id,
                'blocked_date' => '2026-12-25',
            ]);

        ShopBlockedDate::factory()
            ->create([
                'shop_id' => $secondShop->id,
                'blocked_date' => '2026-12-25',
            ]);

        $this->assertDatabaseCount(
            'shop_blocked_dates',
            2
        );
    }

    public function test_hard_deleted_shop_cascades_blocked_dates(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $blockedDate = ShopBlockedDate::factory()
            ->create([
                'shop_id' => $shop->id,
            ]);

        $shop->forceDelete();

        $this->assertDatabaseMissing(
            'shop_blocked_dates',
            [
                'id' => $blockedDate->id,
            ]
        );
    }
}