<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Appointment
            |--------------------------------------------------------------------------
            |
            | Foundation feedback originates from a completed appointment.
            |
            */

            'appointment_id' =>
                Appointment::factory()
                    ->completed(),

            /*
            |--------------------------------------------------------------------------
            | Ownership
            |--------------------------------------------------------------------------
            |
            | These are synchronized with the appointment in configure().
            |
            */

            'customer_id' =>
                null,

            'shop_id' =>
                null,

            /*
            |--------------------------------------------------------------------------
            | Review
            |--------------------------------------------------------------------------
            */

            'rating' =>
                5,

            'comment' =>
                'Great service and a pleasant experience.',
        ];
    }

    /**
     * Synchronize feedback ownership with the appointment.
     */
    public function configure(): static
    {
        return $this->afterMaking(
            function (
                Feedback $feedback
            ): void {
                if (
                    $feedback->appointment_id === null
                ) {
                    return;
                }

                $appointment =
                    Appointment::query()
                        ->find(
                            $feedback->appointment_id
                        );

                if ($appointment === null) {
                    return;
                }

                if (
                    $feedback->customer_id === null
                ) {
                    $feedback->customer_id =
                        $appointment->customer_id;
                }

                if (
                    $feedback->shop_id === null
                ) {
                    $feedback->shop_id =
                        $appointment->shop_id;
                }
            }
        )->afterCreating(
            function (
                Feedback $feedback
            ): void {
                /*
                 * This is mainly defensive for custom test states.
                 */
                $appointment =
                    $feedback->appointment;

                if ($appointment === null) {
                    return;
                }

                $changes = [];

                if (
                    $feedback->customer_id
                    !== $appointment->customer_id
                ) {
                    $changes['customer_id'] =
                        $appointment->customer_id;
                }

                if (
                    $feedback->shop_id
                    !== $appointment->shop_id
                ) {
                    $changes['shop_id'] =
                        $appointment->shop_id;
                }

                if ($changes !== []) {
                    $feedback->forceFill(
                        $changes
                    )->save();
                }
            }
        );
    }

    /**
     * Create a specific star rating.
     */
    public function rating(
        int $rating
    ): static {
        return $this->state(
            fn (array $attributes): array => [
                'rating' =>
                    $rating,
            ]
        );
    }

    /**
     * Create feedback without a written comment.
     */
    public function ratingOnly(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'comment' =>
                    null,
            ]
        );
    }
}