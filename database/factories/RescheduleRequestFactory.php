<?php

namespace Database\Factories;

use App\Enums\RescheduleRequestStatus;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RescheduleRequest>
 */
class RescheduleRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' =>
                Appointment::factory(),

            'requested_date' =>
                now()
                    ->addDays(2)
                    ->toDateString(),

            'requested_starts_at' =>
                '13:00:00',

            'requested_ends_at' =>
                '13:30:00',

            'status' =>
                RescheduleRequestStatus::Pending,

            'customer_reason' =>
                'I need a different appointment time.',

            'rejection_reason' =>
                null,

            'reviewed_by' =>
                null,

            'reviewed_at' =>
                null,
        ];
    }

    public function approved(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    RescheduleRequestStatus::Approved,
            ]
        );
    }

    public function rejected(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    RescheduleRequestStatus::Rejected,

                'rejection_reason' =>
                    'Requested time is unavailable.',
            ]
        );
    }

    public function withdrawn(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    RescheduleRequestStatus::Withdrawn,
            ]
        );
    }
}