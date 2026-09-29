<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Shop;
use App\Models\ShopBreakPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopBreakPeriod>
 */
class ShopBreakPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' =>
                Shop::factory()
                    ->approved(),

            'day_of_week' =>
                DayOfWeek::Monday,

            'starts_at' =>
                '12:00:00',

            'ends_at' =>
                '13:00:00',

            'label' =>
                fake()->optional()
                    ->randomElement([
                        'Lunch break',
                        'Staff break',
                        'Cleaning break',
                    ]),
        ];
    }

    /**
     * Create a recurring break for a specific weekday.
     */
    public function forDay(
        DayOfWeek $day
    ): static {
        return $this->state(
            fn (array $attributes): array => [
                'day_of_week' => $day,
            ]
        );
    }

    /**
     * Configure a specific break interval.
     */
    public function between(
        string $startsAt,
        string $endsAt
    ): static {
        return $this->state(
            fn (array $attributes): array => [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]
        );
    }
}