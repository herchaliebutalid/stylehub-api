<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopBlockedDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopBlockedDate>
 */
class ShopBlockedDateFactory extends Factory
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

            'blocked_date' =>
                now()
                    ->addDays(
                        fake()->numberBetween(
                            1,
                            60
                        )
                    )
                    ->toDateString(),

            'reason' =>
                fake()->optional()
                    ->randomElement([
                        'Holiday closure',
                        'Staff event',
                        'Shop maintenance',
                        'Owner unavailable',
                    ]),
        ];
    }
}