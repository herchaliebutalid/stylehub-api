<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Test users are verified by default so existing feature tests continue
     * to represent established accounts.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' =>
                fake()->firstName(),

            'middle_name' =>
                null,

            'last_name' =>
                fake()->lastName(),

            'email' =>
                fake()
                    ->unique()
                    ->safeEmail(),

            'phone_number' =>
                '+639'
                .fake()
                    ->unique()
                    ->numerify(
                        '#########'
                    ),

            'role' =>
                UserRole::Customer,

            'status' =>
                UserStatus::Active,

            'email_verified_at' =>
                now(),

            'phone_verified_at' =>
                now(),

            'last_login_at' =>
                null,

            'password' =>
                static::$password
                ??= Hash::make(
                    'password'
                ),

            'remember_token' =>
                Str::random(10),
        ];
    }

    /**
     * Indicate that the user's email address has not been verified.
     */
    public function unverified(): static
    {
        return $this->state(
            fn (
                array $attributes
            ): array => [
                'email_verified_at' =>
                    null,
            ]
        );
    }

    /**
     * Indicate that the user's phone number has not been verified.
     */
    public function unverifiedPhone(): static
    {
        return $this->state(
            fn (
                array $attributes
            ): array => [
                'phone_verified_at' =>
                    null,
            ]
        );
    }

    /**
     * Create a customer account.
     */
    public function customer(): static
    {
        return $this->state(
            fn (
                array $attributes
            ): array => [
                'role' =>
                    UserRole::Customer,
            ]
        );
    }

    /**
     * Create a shop-owner account.
     */
    public function shopOwner(): static
    {
        return $this->state(
            fn (
                array $attributes
            ): array => [
                'role' =>
                    UserRole::ShopOwner,
            ]
        );
    }

    /**
     * Create an administrator account.
     */
    public function admin(): static
    {
        return $this->state(
            fn (
                array $attributes
            ): array => [
                'role' =>
                    UserRole::Admin,
            ]
        );
    }

    /**
     * Create a suspended account.
     */
    public function suspended(): static
    {
        return $this->state(
            fn (
                array $attributes
            ): array => [
                'status' =>
                    UserStatus::Suspended,
            ]
        );
    }
}