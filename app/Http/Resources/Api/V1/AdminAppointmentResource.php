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
        $customer = $this->relationLoaded('customer')
            ? $this->customer
            : null;

        $shop = $this->relationLoaded('shop')
            ? $this->shop
            : null;

        $service = $this->relationLoaded('service')
            ? $this->service
            : null;

        return [
            /*
            |--------------------------------------------------------------------------
            | Appointment Information
            |--------------------------------------------------------------------------
            */

            'id' => $this->id,

            'status' => $this->enumValue(
                $this->status
            ),

            'appointment_date' => $this->dateOnlyValue(
                $this->appointment_date
            ),

            /*
            |--------------------------------------------------------------------------
            | Correct Database Time Fields
            |--------------------------------------------------------------------------
            |
            | DATABASE:
            |   starts_at
            |   ends_at
            |
            | ADMIN VUE API RESPONSE:
            |   start_time
            |   end_time
            |
            | Preserve the existing frontend field names.
            |
            */

            'start_time' => $this->stringValue(
                $this->starts_at
            ),

            'end_time' => $this->stringValue(
                $this->ends_at
            ),

            /*
            |--------------------------------------------------------------------------
            | Customer Information
            |--------------------------------------------------------------------------
            */

            'customer' => $customer === null
                ? null
                : [
                    'id' => $customer->id,

                    'full_name' => $this->fullName(
                        $customer
                    ),

                    'email' => $customer->email,

                    'phone_number' =>
                        $customer->phone_number,

                    'status' => $this->enumValue(
                        $customer->status
                    ),
                ],

            /*
            |--------------------------------------------------------------------------
            | Shop Information
            |--------------------------------------------------------------------------
            */

            'shop' => $shop === null
                ? null
                : [
                    'id' => $shop->id,

                    'name' => $shop->name,

                    'city' => $shop->city,

                    'province' => $shop->province,

                    'approval_status' => $this->enumValue(
                        $shop->approval_status
                    ),

                    'availability_status' => $this->enumValue(
                        $shop->availability_status
                    ),
                ],

            /*
            |--------------------------------------------------------------------------
            | Current Service Information
            |--------------------------------------------------------------------------
            |
            | The service may be edited or retired
            | after an appointment was booked.
            |
            | Historical booking values are preserved
            | separately in booking_snapshot.
            |
            */

            'service' => $service === null
                ? null
                : [
                    'id' => $service->id,

                    'name' => $service->name,

                    'price' => $service->price,

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
            | Use the original booked service values.
            |
            | Correct StyleHub database columns:
            |   service_name
            |   price_snapshot
            |   duration_minutes_snapshot
            |
            | Preserve the existing JSON response format.
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
                            'duration_minutes_snapshot',
                            'service_duration_snapshot',
                            'duration_snapshot',
                        ],
                        $service?->duration_minutes
                    ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Additional Appointment Details
            |--------------------------------------------------------------------------
            */

            'notes' => $this->notes,

            'reschedule_requests_count' =>
                isset($this->reschedule_requests_count)
                    ? (int) $this->reschedule_requests_count
                    : 0,

            /*
            |--------------------------------------------------------------------------
            | Record Timestamps
            |--------------------------------------------------------------------------
            */

            'created_at' => $this->dateTimeValue(
                $this->created_at
            ),

            'updated_at' => $this->dateTimeValue(
                $this->updated_at
            ),
        ];
    }

    /**
     * Build a readable customer full name.
     */
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

    /**
     * Retrieve the first existing model attribute.
     *
     * This maintains compatibility with historical
     * snapshot column names where applicable.
     */
    private function firstExistingAttribute(
        array $attributeNames,
        mixed $fallback = null
    ): mixed {
        $attributes = $this->resource
            ->getAttributes();

        foreach ($attributeNames as $attributeName) {
            if (
                array_key_exists(
                    $attributeName,
                    $attributes
                )
            ) {
                return $this->resource
                    ->getAttribute($attributeName);
            }
        }

        return $fallback;
    }

    /**
     * Convert backed enum values to strings.
     */
    private function enumValue(
        mixed $value
    ): mixed {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }

    /**
     * Format appointment start/end times.
     */
    private function stringValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        return (string) $value;
    }

    /**
     * Format a date as YYYY-MM-DD.
     */
    private function dateOnlyValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    /**
     * Format database timestamps.
     */
    private function dateTimeValue(
        mixed $value
    ): ?string {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }
}
