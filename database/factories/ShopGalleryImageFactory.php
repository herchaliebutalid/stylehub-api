<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopGalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopGalleryImage>
 */
class ShopGalleryImageFactory extends Factory
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

            'image_path' =>
                'shop-gallery/'
                .fake()->uuid()
                .'.jpg',

            'position' =>
                fake()->numberBetween(
                    0,
                    9
                ),
        ];
    }
}