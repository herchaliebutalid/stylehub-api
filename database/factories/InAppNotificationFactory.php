<?php

namespace Database\Factories;

use App\Enums\InAppNotificationType;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InAppNotification>
 */
class InAppNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' =>
                User::factory()
                    ->customer(),

            'type' =>
                InAppNotificationType::BookingReceived,

            'title' =>
                'New Appointment',

            'message' =>
                'A new StyleHub appointment requires your attention.',

            'appointment_id' =>
                null,

            'reschedule_request_id' =>
                null,

            'feedback_id' =>
                null,

            'promo_id' =>
                null,

            'dedupe_key' =>
                null,

            'read_at' =>
                null,
        ];
    }

    /**
     * Create a notification that has already been read.
     */
    public function read(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'read_at' =>
                    now(),
            ]
        );
    }
}