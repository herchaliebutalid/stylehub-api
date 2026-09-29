<?php

namespace Database\Factories;

use App\Enums\ShopAdministrativeStatus;
use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\ShopType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()
                ->shopOwner(),

            'name' => fake()->company()
                .' Barber Shop',

            'type' => ShopType::BarberShop,

            'description' => fake()->sentence(),

            'phone_number' => '+639'
                .fake()->numerify('#########'),

            'email' => fake()
                ->unique()
                ->safeEmail(),

            'address' => fake()->streetAddress(),

            'city' => 'Surigao City',

            'province' => 'Surigao del Norte',

            'availability_status' =>
                ShopAvailabilityStatus::Closed,

            'approval_status' =>
                ShopApprovalStatus::Pending,

            /*
             * New shops begin administratively active.
             *
             * Approval is still a separate requirement.
             */
            'administrative_status' =>
                ShopAdministrativeStatus::Active,

            'reviewed_by' => null,

            'reviewed_at' => null,

            'rejection_reason' => null,

            'suspended_by' => null,

            'suspended_at' => null,

            'suspension_reason' => null,
        ];
    }

    /**
     * Create a barber shop.
     */
    public function barberShop(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'type' =>
                    ShopType::BarberShop,
            ]
        );
    }

    /**
     * Create a beauty parlor.
     */
    public function beautyParlor(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'type' =>
                    ShopType::BeautyParlor,

                'name' =>
                    fake()->company()
                    .' Beauty Parlor',
            ]
        );
    }

    /**
     * Create an approved shop.
     */
    public function approved(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'approval_status' =>
                    ShopApprovalStatus::Approved,

                'reviewed_by' =>
                    User::factory()
                        ->admin(),

                'reviewed_at' =>
                    now(),

                'rejection_reason' =>
                    null,
            ]
        );
    }

    /**
     * Create a rejected shop.
     */
    public function rejected(
        string $reason = 'Registration requirements were not satisfied.'
    ): static {
        return $this->state(
            fn (array $attributes): array => [
                'approval_status' =>
                    ShopApprovalStatus::Rejected,

                'reviewed_by' =>
                    User::factory()
                        ->admin(),

                'reviewed_at' =>
                    now(),

                'rejection_reason' =>
                    $reason,
            ]
        );
    }

    /**
     * Create an administratively suspended shop.
     *
     * This does not alter the Shop Owner account status.
     */
    public function suspended(
        string $reason = 'Shop temporarily suspended by administrator.'
    ): static {
        return $this->state(
            fn (array $attributes): array => [
                'administrative_status' =>
                    ShopAdministrativeStatus::Suspended,

                'suspended_by' =>
                    User::factory()
                        ->admin(),

                'suspended_at' =>
                    now(),

                'suspension_reason' =>
                    $reason,
            ]
        );
    }

    /**
     * Create an open shop.
     */
    public function open(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'availability_status' =>
                    ShopAvailabilityStatus::Open,
            ]
        );
    }
}