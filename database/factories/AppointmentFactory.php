<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' =>
                User::factory()
                    ->customer(),

            'shop_id' =>
                Shop::factory()
                    ->approved(),

            'service_id' =>
                function (
                    array $attributes
                ): int {
                    return Service::factory()
                        ->create([
                            'shop_id' =>
                                $attributes['shop_id'],

                            'name' =>
                                'Classic Haircut',

                            'price' =>
                                '250.00',

                            'duration_minutes' =>
                                30,

                            'is_active' =>
                                true,
                        ])
                        ->id;
                },

            'appointment_date' =>
                now()
                    ->addDay()
                    ->toDateString(),

            'starts_at' =>
                '10:00:00',

            'ends_at' =>
                '10:30:00',

            'service_name' =>
                'Classic Haircut',

            'price_snapshot' =>
                '250.00',

            'duration_minutes_snapshot' =>
                30,

            'status' =>
                AppointmentStatus::Pending,

            'notes' => null,

            'cancellation_reason' => null,

            'cancelled_by' => null,

            'cancelled_at' => null,

            'completed_at' => null,
        ];
    }

    /**
     * Create a confirmed appointment.
     */
    public function confirmed(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    AppointmentStatus::Confirmed,
            ]
        );
    }

    /**
     * Create a completed appointment.
     */
    public function completed(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    AppointmentStatus::Completed,

                'completed_at' =>
                    now(),
            ]
        );
    }

    /**
     * Create a cancelled appointment.
     */
    public function cancelled(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    AppointmentStatus::Cancelled,

                'cancellation_reason' =>
                    'Appointment was cancelled.',

                'cancelled_at' =>
                    now(),
            ]
        );
    }

    /**
     * Create a declined appointment.
     */
    public function declined(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    AppointmentStatus::Declined,

                'cancellation_reason' =>
                    'Appointment was declined.',

                'cancelled_at' =>
                    now(),
            ]
        );
    }
}