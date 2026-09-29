<?php

namespace App\Http\Resources\Api\V1;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SmsNotification
 */
class AdminSmsNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $user =
            $this->relationLoaded('user')
                ? $this->user
                : null;

        $appointment =
            $this->relationLoaded('appointment')
                ? $this->appointment
                : null;

        $shop =
            $appointment !== null
            && $appointment->relationLoaded('shop')
                ? $appointment->shop
                : null;

        return [
            'id' =>
                $this->id,

            'type' =>
                $this->enumValue(
                    $this->type
                ),

            'type_label' =>
                $this->type?->label(),

            'status' =>
                $this->enumValue(
                    $this->status
                ),

            'status_label' =>
                $this->status?->label(),

            /*
            |--------------------------------------------------------------------------
            | Recipient Privacy
            |--------------------------------------------------------------------------
            */

            'recipient' =>
                $this->maskedRecipient(),

            'provider' =>
                $this->provider,

            'provider_status' =>
                $this->provider_status,

            'attempts' =>
                (int) $this->attempts,

            'failure_kind' =>
                $this->enumValue(
                    $this->failure_kind
                ),

            'failure_kind_label' =>
                $this->failure_kind?->label(),

            'can_retry' =>
                $this->isSafeToRetry(),

            'last_error' =>
                $this->last_error,

            'user' =>
                $user === null
                    ? null
                    : [
                        'id' =>
                            $user->id,

                        'full_name' =>
                            $user->full_name,

                        'email' =>
                            $user->email,

                        'role' =>
                            $this->enumValue(
                                $user->role
                            ),

                        'status' =>
                            $this->enumValue(
                                $user->status
                            ),
                    ],

            'appointment' =>
                $appointment === null
                    ? null
                    : [
                        'id' =>
                            $appointment->id,

                        'status' =>
                            $this->enumValue(
                                $appointment->status
                            ),

                        'appointment_date' =>
                            $appointment
                                ->appointment_date
                                ?->toDateString(),

                        'starts_at' =>
                            $appointment->starts_at,

                        'shop' =>
                            $shop === null
                                ? null
                                : [
                                    'id' =>
                                        $shop->id,

                                    'name' =>
                                        $shop->name,
                                ],
                    ],

            'last_attempt_at' =>
                $this->dateTimeValue(
                    $this->last_attempt_at
                ),

            'accepted_at' =>
                $this->dateTimeValue(
                    $this->accepted_at
                ),

            'failed_at' =>
                $this->dateTimeValue(
                    $this->failed_at
                ),

            'created_at' =>
                $this->dateTimeValue(
                    $this->created_at
                ),

            'updated_at' =>
                $this->dateTimeValue(
                    $this->updated_at
                ),
        ];
    }

    protected function enumValue(
        mixed $value
    ): mixed {
        return $value instanceof BackedEnum
            ? $value->value
            : $value;
    }

    protected function dateTimeValue(
        mixed $value
    ): ?string {
        if ($value instanceof DateTimeInterface) {
            return $value->format(
                DATE_ATOM
            );
        }

        return is_string($value)
            ? $value
            : null;
    }

    protected function maskedRecipient(): string
    {
        $recipient =
            (string) $this->recipient;

        if (
            $recipient === ''
            || $recipient === 'unavailable'
        ) {
            return 'Unavailable';
        }

        $length =
            mb_strlen(
                $recipient
            );

        if ($length <= 7) {
            return str_repeat(
                '*',
                $length
            );
        }

        return mb_substr(
            $recipient,
            0,
            4
        )
            .str_repeat(
                '*',
                max(
                    1,
                    $length - 7
                )
            )
            .mb_substr(
                $recipient,
                -3
            );
    }
}