<?php

namespace App\Http\Resources\Api\V1;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Feedback
 */
class AdminFeedbackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $customer =
            $this->relationLoaded(
                'customer'
            )
                ? $this->customer
                : null;

        $shop =
            $this->relationLoaded(
                'shop'
            )
                ? $this->shop
                : null;

        $appointment =
            $this->relationLoaded(
                'appointment'
            )
                ? $this->appointment
                : null;

        return [
            'id' =>
                $this->id,

            'rating' =>
                (int) $this->rating,

            'comment' =>
                $this->comment,

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            |
            | Only safe operational information is returned.
            |
            */

            'customer' =>
                $customer === null
                    ? null
                    : [
                        'id' =>
                            $customer->id,

                        'full_name' =>
                            $this->fullName(
                                $customer
                            ),

                        'email' =>
                            $customer->email,

                        'status' =>
                            $this->enumValue(
                                $customer->status
                            ),
                    ],

            /*
            |--------------------------------------------------------------------------
            | Shop
            |--------------------------------------------------------------------------
            */

            'shop' =>
                $shop === null
                    ? null
                    : [
                        'id' =>
                            $shop->id,

                        'name' =>
                            $shop->name,

                        'city' =>
                            $shop->city,

                        'province' =>
                            $shop->province,

                        'approval_status' =>
                            $this->enumValue(
                                $shop->approval_status
                            ),
                    ],

            /*
            |--------------------------------------------------------------------------
            | Appointment
            |--------------------------------------------------------------------------
            |
            | Feedback is tied to the completed booking that produced it.
            |
            */

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
                            $this->dateOnlyValue(
                                $appointment
                                    ->appointment_date
                            ),

                        'start_time' =>
                            $this->stringValue(
                                $appointment
                                    ->start_time
                            ),
                    ],

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

    private function fullName(
        object $user
    ): string {
        return trim(
            implode(
                ' ',
                array_filter([
                    $user->first_name,
                    $user->middle_name,
                    $user->last_name,
                ])
            )
        );
    }

    private function enumValue(
        mixed $value
    ): mixed {
        if (
            $value instanceof BackedEnum
        ) {
            return $value->value;
        }

        return $value;
    }

    private function dateOnlyValue(
        mixed $value
    ): ?string {
        if (
            $value === null
        ) {
            return null;
        }

        if (
            $value instanceof DateTimeInterface
        ) {
            return $value->format(
                'Y-m-d'
            );
        }

        return (string) $value;
    }

    private function stringValue(
        mixed $value
    ): ?string {
        if (
            $value === null
        ) {
            return null;
        }

        if (
            $value instanceof DateTimeInterface
        ) {
            return $value->format(
                'H:i:s'
            );
        }

        return (string) $value;
    }

    private function dateTimeValue(
        mixed $value
    ): ?string {
        if (
            $value instanceof DateTimeInterface
        ) {
            return $value->format(
                DATE_ATOM
            );
        }

        if (
            is_string(
                $value
            )
        ) {
            return $value;
        }

        return null;
    }
}