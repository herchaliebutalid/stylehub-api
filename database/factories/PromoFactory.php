<?php

namespace Database\Factories;

use App\Enums\PromoDiscountType;
use App\Models\Promo;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promo>
 */
class PromoFactory extends Factory
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

            /*
             * null means the promotion applies to the entire shop.
             */
            'service_id' =>
                null,

            'title' =>
                'StyleHub Special Offer',

            'description' =>
                'Enjoy a limited-time discount on selected services.',

            'discount_type' =>
                PromoDiscountType::Percentage,

            'discount_value' =>
                '10.00',

            'starts_at' =>
                now()
                    ->subDay(),

            'ends_at' =>
                now()
                    ->addWeek(),

            'is_active' =>
                true,
        ];
    }

    /**
     * Create a fixed-amount discount.
     */
    public function fixedAmount(
        string $amount = '100.00'
    ): static {
        return $this->state(
            fn (array $attributes): array => [
                'discount_type' =>
                    PromoDiscountType::FixedAmount,

                'discount_value' =>
                    $amount,
            ]
        );
    }

    /**
     * Create a percentage discount.
     */
    public function percentage(
        string $percentage = '10.00'
    ): static {
        return $this->state(
            fn (array $attributes): array => [
                'discount_type' =>
                    PromoDiscountType::Percentage,

                'discount_value' =>
                    $percentage,
            ]
        );
    }

    /**
     * Create a manually disabled promo.
     */
    public function inactive(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'is_active' =>
                    false,
            ]
        );
    }

    /**
     * Create a future promotion.
     */
    public function upcoming(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'starts_at' =>
                    now()
                        ->addDay(),

                'ends_at' =>
                    now()
                        ->addWeek(),
            ]
        );
    }

    /**
     * Create an expired promotion.
     */
    public function expired(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'starts_at' =>
                    now()
                        ->subWeek(),

                'ends_at' =>
                    now()
                        ->subDay(),
            ]
        );
    }
}