<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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

            'name' => fake()->randomElement([
                'Classic Haircut',
                'Hair Coloring',
                'Hair Treatment',
                'Manicure',
                'Pedicure',
                'Facial Treatment',
            ]),

            'description' =>
                fake()->sentence(),

            'price' =>
                fake()->randomFloat(
                    2,
                    100,
                    5000
                ),

            'duration_minutes' =>
                fake()->randomElement([
                    30,
                    45,
                    60,
                    90,
                    120,
                ]),

            'is_active' => true,
        ];
    }

    /**
     * Create an inactive service.
     */
    public function inactive(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'is_active' => false,
            ]
        );
    }
}