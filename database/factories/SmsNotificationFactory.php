<?php

namespace Database\Factories;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use App\Models\SmsNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsNotification>
 */
class SmsNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' =>
                User::factory()
                    ->customer(),

            'appointment_id' =>
                null,

            'type' =>
                SmsNotificationType::BookingReceived,

            'recipient' =>
                '09171234567',

            'message' =>
                'StyleHub: Your appointment request has been received.',

            'status' =>
                SmsNotificationStatus::Pending,

            'provider' =>
                'semaphore',

            'provider_message_id' =>
                null,

            'provider_status' =>
                null,

            'dedupe_key' =>
                null,

            'failure_kind' =>
                null,

            'attempts' =>
                0,

            'last_attempt_at' =>
                null,

            'accepted_at' =>
                null,

            'failed_at' =>
                null,

            'last_error' =>
                null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    SmsNotificationStatus::Accepted,

                'provider_message_id' =>
                    '123456',

                'provider_status' =>
                    'Queued',

                'failure_kind' =>
                    null,

                'attempts' =>
                    1,

                'last_attempt_at' =>
                    now(),

                'accepted_at' =>
                    now(),

                'failed_at' =>
                    null,

                'last_error' =>
                    null,
            ]
        );
    }

    /**
     * Failure for which a deliberate retry is considered safe.
     */
    public function failed(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    SmsNotificationStatus::Failed,

                'failure_kind' =>
                    SmsFailureKind::Definite,

                'attempts' =>
                    1,

                'last_attempt_at' =>
                    now(),

                'failed_at' =>
                    now(),

                'last_error' =>
                    'SMS provider rejected the message.',
            ]
        );
    }

    /**
     * Failure whose actual provider outcome is unknown.
     */
    public function uncertainFailure(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'status' =>
                    SmsNotificationStatus::Failed,

                'failure_kind' =>
                    SmsFailureKind::Uncertain,

                'attempts' =>
                    1,

                'last_attempt_at' =>
                    now(),

                'failed_at' =>
                    now(),

                'last_error' =>
                    'SMS delivery outcome is uncertain.',
            ]
        );
    }
}