<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * Transform an appointment for the customer API.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'appointment_date' =>
                $this->appointment_date
                    ->toDateString(),

            'starts_at' =>
                $this->starts_at,

            'ends_at' =>
                $this->ends_at,

            'status' =>
                $this->status->value,

            'status_label' =>
                $this->status->label(),

            'service_snapshot' => [
                'name' =>
                    $this->service_name,

                'price' =>
                    $this->price_snapshot,

                'duration_minutes' =>
                    $this->duration_minutes_snapshot,
            ],

            'shop' => $this->whenLoaded(
                'shop',
                fn (): array => [
                    'id' => $this->shop->id,
                    'name' => $this->shop->name,

                    'type' =>
                        $this->shop->type->value,

                    'type_label' =>
                        $this->shop->type->label(),
                ]
            ),

            'service' => $this->whenLoaded(
                'service',
                fn (): array => [
                    'id' =>
                        $this->service->id,

                    'name' =>
                        $this->service->name,
                ]
            ),

            'notes' =>
                $this->notes,

            'cancellation_reason' =>
                $this->cancellation_reason,

            'cancelled_at' =>
                $this->cancelled_at
                    ?->toISOString(),

            'completed_at' =>
                $this->completed_at
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