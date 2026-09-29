<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RescheduleRequest
 */
class RescheduleRequestResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'appointment_id' =>
                $this->appointment_id,

            'requested_date' =>
                $this->requested_date
                    ->toDateString(),

            'requested_starts_at' =>
                $this->requested_starts_at,

            'requested_ends_at' =>
                $this->requested_ends_at,

            'status' =>
                $this->status->value,

            'status_label' =>
                $this->status->label(),

            'customer_reason' =>
                $this->customer_reason,

            'rejection_reason' =>
                $this->rejection_reason,

            'reviewed_at' =>
                $this->reviewed_at
                    ?->toISOString(),

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

                        'service_name' =>
                            $this->appointment
                                ->service_name,

                        'shop' => [
                            'id' =>
                                $this->appointment
                                    ->shop
                                    ->id,

                            'name' =>
                                $this->appointment
                                    ->shop
                                    ->name,
                        ],
                    ]
                ),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}