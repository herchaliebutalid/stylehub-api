<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\Shop;
use App\Models\ShopSchedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopScheduleFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_schedule_has_valid_weekday_and_hours(): void
    {
        $schedule = ShopSchedule::factory()
            ->forDay(DayOfWeek::Monday)
            ->openBetween(
                '09:00:00',
                '18:00:00'
            )
            ->create();

        $this->assertSame(
            DayOfWeek::Monday,
            $schedule->day_of_week
        );

        $this->assertTrue(
            $schedule->is_open
        );

        $this->assertTrue(
            $schedule->isOpen()
        );

        $this->assertFalse(
            $schedule->isClosed()
        );

        $this->assertTrue(
            $schedule->hasOperatingHours()
        );

        $this->assertSame(
            '09:00:00',
            $schedule->opens_at
        );

        $this->assertSame(
            '18:00:00',
            $schedule->closes_at
        );
    }

    public function test_closed_schedule_has_no_operating_hours(): void
    {
        $schedule = ShopSchedule::factory()
            ->forDay(DayOfWeek::Sunday)
            ->closed()
            ->create();

        $this->assertFalse(
            $schedule->is_open
        );

        $this->assertFalse(
            $schedule->isOpen()
        );

        $this->assertTrue(
            $schedule->isClosed()
        );

        $this->assertFalse(
            $schedule->hasOperatingHours()
        );

        $this->assertNull(
            $schedule->opens_at
        );

        $this->assertNull(
            $schedule->closes_at
        );
    }

    public function test_schedule_belongs_to_shop(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $schedule = ShopSchedule::factory()
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->assertSame(
            $shop->id,
            $schedule->shop->id
        );
    }

    public function test_shop_has_many_schedules(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        foreach (
            DayOfWeek::ordered()
            as $day
        ) {
            ShopSchedule::factory()
                ->forDay($day)
                ->create([
                    'shop_id' => $shop->id,
                ]);
        }

        $this->assertCount(
            7,
            $shop->fresh()->schedules
        );
    }

    public function test_shop_id_cannot_be_mass_assigned_to_schedule(): void
    {
        $schedule = new ShopSchedule();

        $schedule->fill([
            'shop_id' => 999,
            'day_of_week' =>
                DayOfWeek::Monday->value,
            'is_open' => true,
            'opens_at' => '09:00:00',
            'closes_at' => '18:00:00',
        ]);

        $this->assertNull(
            $schedule->shop_id
        );

        $this->assertSame(
            DayOfWeek::Monday,
            $schedule->day_of_week
        );
    }

    public function test_shop_cannot_have_duplicate_schedule_for_same_day(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        ShopSchedule::factory()
            ->forDay(DayOfWeek::Monday)
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->expectException(
            QueryException::class
        );

        ShopSchedule::factory()
            ->forDay(DayOfWeek::Monday)
            ->create([
                'shop_id' => $shop->id,
            ]);
    }

    public function test_day_of_week_enum_contains_all_seven_days_in_order(): void
    {
        $days = DayOfWeek::ordered();

        $this->assertCount(
            7,
            $days
        );

        $this->assertSame(
            DayOfWeek::Monday,
            $days[0]
        );

        $this->assertSame(
            DayOfWeek::Sunday,
            $days[6]
        );

        $this->assertSame(
            1,
            DayOfWeek::Monday->value
        );

        $this->assertSame(
            7,
            DayOfWeek::Sunday->value
        );
    }
}