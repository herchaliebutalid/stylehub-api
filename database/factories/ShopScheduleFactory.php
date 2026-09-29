<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Shop;
use App\Models\ShopSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopSchedule>
 */
class ShopScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory()
                ->approved(),

            'day_of_week' =>
                DayOfWeek::Monday,

            'is_open' => true,

            'opens_at' => '09:00:00',

            'closes_at' => '18:00:00',
        ];
    }

    /**
     * Create a schedule for a specific weekday.
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
     * Create a closed day.
     */
    public function closed(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'is_open' => false,
                'opens_at' => null,
                'closes_at' => null,
            ]
        );
    }

    /**
     * Configure specific operating hours.
     */
    public function openBetween(
        string $opensAt,
        string $closesAt
    ): static {
        return $this->state(
            fn (array $attributes): array => [
                'is_open' => true,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
            ]
        );
    }
}