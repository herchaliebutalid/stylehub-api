<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RescheduleRequest
 */
class ShopOwnerRescheduleRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'status' =>
                $this->status->value,

            'status_label' =>
                $this->status->label(),

            'requested_date' =>
                $this->requested_date
                    ->toDateString(),

            'requested_starts_at' =>
                $this->requested_starts_at,

            'requested_ends_at' =>
                $this->requested_ends_at,

            'customer_reason' =>
                $this->customer_reason,

            'rejection_reason' =>
                $this->rejection_reason,

            'appointment' =>
                $this->whenLoaded(
                    'appointment',
                    fn (): array => [
                        'id' =>
                            $this->appointment->id,

                        'appointment_date' =>
                            $this->appointment
                                ->appointment_date
                                ->toDateString(),

                        'starts_at' =>
                            $this->appointment
                                ->starts_at,

                        'ends_at' =>
                            $this->appointment
                                ->ends_at,

                        'status' =>
                            $this->appointment
                                ->status
                                ->value,

                        'service_snapshot' => [
                            'name' =>
                                $this->appointment
                                    ->service_name,

                            'price' =>
                                $this->appointment
                                    ->price_snapshot,

                            'duration_minutes' =>
                                $this->appointment
                                    ->duration_minutes_snapshot,
                        ],

                        'customer' =>
                            $this->appointment
                                ->relationLoaded(
                                    'customer'
                                )
                                ? [
                                    'id' =>
                                        $this->appointment
                                            ->customer
                                            ->id,

                                    'full_name' =>
                                        $this->appointment
                                            ->customer
                                            ->full_name,

                                    'email' =>
                                        $this->appointment
                                            ->customer
                                            ->email,

                                    'phone_number' =>
                                        $this->appointment
                                            ->customer
                                            ->phone_number,
                                ]
                                : null,
                    ]
                ),

            'reviewer' =>
                $this->whenLoaded(
                    'reviewer',
                    fn (): array => [
                        'id' =>
                            $this->reviewer->id,

                        'full_name' =>
                            $this->reviewer
                                ->full_name,
                    ]
                ),

            'reviewed_at' =>
                $this->reviewed_at
                    ?->toISOString(),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}