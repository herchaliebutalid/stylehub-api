<?php

namespace App\Http\Resources\Api\V1;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Appointment
 */
class AdminAppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $customer =
            $this->relationLoaded('customer')
                ? $this->customer
                : null;

        $shop =
            $this->relationLoaded('shop')
                ? $this->shop
                : null;

        $service =
            $this->relationLoaded('service')
                ? $this->service
                : null;

        return [
            'id' =>
                $this->id,

            'status' =>
                $this->enumValue(
                    $this->status
                ),

            'appointment_date' =>
                $this->dateOnlyValue(
                    $this->appointment_date
                ),

            'start_time' =>
                $this->stringValue(
                    $this->start_time
                ),

            'end_time' =>
                $this->stringValue(
                    $this->end_time
                ),

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            |
            | Administrator-only operational information.
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

                        'phone_number' =>
                            $customer->phone_number,

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

                        'availability_status' =>
                            $this->enumValue(
                                $shop->availability_status
                            ),
                    ],

            /*
            |--------------------------------------------------------------------------
            | Current Service Record
            |--------------------------------------------------------------------------
            |
            | The service may have changed after the appointment was booked.
            |
            | The booking_snapshot section below preserves the booking-time
            | values when those snapshot columns are available.
            |
            */

            'service' =>
                $service === null
                    ? null
                    : [
                        'id' =>
                            $service->id,

                        'name' =>
                            $service->name,

                        'price' =>
                            $service->price,

                        'duration_minutes' =>
                            $service->duration_minutes,

                        'is_active' =>
                            (bool) $service->is_active,
                    ],

            /*
            |--------------------------------------------------------------------------
            | Booking Snapshot
            |--------------------------------------------------------------------------
            |
            | StyleHub has used snapshot fields to prevent later service
            | changes from rewriting appointment history.
            |
            | The helper safely supports the snapshot naming used by the
            | existing appointment schema without exposing internal details.
            |
            */

            'booking_snapshot' => [
                'service_name' =>
                    $this->firstExistingAttribute(
                        [
                            'service_name_snapshot',
                            'service_name',
                        ],
                        $service?->name
                    ),

                'price' =>
                    $this->firstExistingAttribute(
                        [
                            'service_price_snapshot',
                            'price_snapshot',
                        ],
                        $service?->price
                    ),

                'duration_minutes' =>
                    $this->firstExistingAttribute(
                        [
                            'service_duration_snapshot',
                            'duration_snapshot',
                        ],
                        $service?->duration_minutes
                    ),
            ],

            'notes' =>
                $this->notes,

            'reschedule_requests_count' =>
                isset(
                    $this->reschedule_requests_count
                )
                    ? (int) $this->reschedule_requests_count
                    : 0,

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

    private function firstExistingAttribute(
        array $attributeNames,
        mixed $fallback = null
    ): mixed {
        $attributes =
            $this->resource
                ->getAttributes();

        foreach (
            $attributeNames
            as $attributeName
        ) {
            if (
                array_key_exists(
                    $attributeName,
                    $attributes
                )
            ) {
                return $this->resource
                    ->getAttribute(
                        $attributeName
                    );
            }
        }

        return $fallback;
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