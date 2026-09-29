<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\Shop;
use App\Models\ShopBreakPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopBreakPeriodFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_break_period_schema_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'shop_break_periods',
                [
                    'id',
                    'shop_id',
                    'day_of_week',
                    'starts_at',
                    'ends_at',
                    'label',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_break_period_belongs_to_shop_and_shop_has_break_periods(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $breakPeriod = ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('12:00:00', '13:00:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->assertSame(
            $shop->id,
            $breakPeriod->shop->id
        );

        $this->assertTrue(
            $shop
                ->breakPeriods()
                ->whereKey($breakPeriod->id)
                ->exists()
        );
    }

    public function test_day_of_week_is_cast_to_enum_and_times_are_normalized(): void
    {
        $breakPeriod = ShopBreakPeriod::factory()
            ->create([
                'day_of_week' => DayOfWeek::Wednesday,
                'starts_at' => '12:15',
                'ends_at' => '13:30',
            ]);

        $this->assertSame(
            DayOfWeek::Wednesday,
            $breakPeriod->day_of_week
        );

        $this->assertSame(
            '12:15:00',
            $breakPeriod->starts_at
        );

        $this->assertSame(
            '13:30:00',
            $breakPeriod->ends_at
        );
    }

    public function test_break_periods_are_ordered_by_weekday_then_start_time(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $tuesday = ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Tuesday)
            ->between('10:00:00', '10:30:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $mondayLater = ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('15:00:00', '15:30:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $mondayEarlier = ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('12:00:00', '13:00:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->assertSame(
            [
                $mondayEarlier->id,
                $mondayLater->id,
                $tuesday->id,
            ],
            $shop
                ->breakPeriods()
                ->ordered()
                ->pluck('id')
                ->all()
        );
    }

    public function test_shop_id_cannot_be_mass_assigned_to_break_period(): void
    {
        $breakPeriod = new ShopBreakPeriod();

        $breakPeriod->fill([
            'shop_id' => 999,
            'day_of_week' => DayOfWeek::Monday->value,
            'starts_at' => '12:00',
            'ends_at' => '13:00',
            'label' => 'Lunch',
        ]);

        $this->assertNull(
            $breakPeriod->shop_id
        );

        $this->assertSame(
            DayOfWeek::Monday,
            $breakPeriod->day_of_week
        );
    }

    public function test_exact_duplicate_break_period_is_rejected_by_database(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('12:00:00', '13:00:00')
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->expectException(
            QueryException::class
        );

        ShopBreakPeriod::factory()
            ->forDay(DayOfWeek::Monday)
            ->between('12:00:00', '13:00:00')
            ->create([
                'shop_id' => $shop->id,
            ]);
    }

    public function test_different_shops_may_use_the_same_break_period(): void
    {
        $firstShop = Shop::factory()
            ->approved()
            ->create();

        $secondShop = Shop::factory()
            ->approved()
            ->create();

        foreach ([$firstShop, $secondShop] as $shop) {
            ShopBreakPeriod::factory()
                ->forDay(DayOfWeek::Monday)
                ->between('12:00:00', '13:00:00')
                ->create([
                    'shop_id' => $shop->id,
                ]);
        }

        $this->assertDatabaseCount(
            'shop_break_periods',
            2
        );
    }

    public function test_hard_deleted_shop_cascades_break_periods(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $breakPeriod = ShopBreakPeriod::factory()
            ->create([
                'shop_id' => $shop->id,
            ]);

        $shop->forceDelete();

        $this->assertDatabaseMissing(
            'shop_break_periods',
            [
                'id' => $breakPeriod->id,
            ]
        );
    }
}