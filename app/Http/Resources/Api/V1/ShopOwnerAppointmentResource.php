<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Appointment
 */
class ShopOwnerAppointmentResource extends JsonResource
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

            'customer' =>
                $this->whenLoaded(
                    'customer',
                    fn (): array => [
                        'id' =>
                            $this->customer->id,

                        'full_name' =>
                            $this->customer->full_name,

                        'email' =>
                            $this->customer->email,

                        'phone_number' =>
                            $this->customer->phone_number,
                    ]
                ),

            'service_snapshot' => [
                'name' =>
                    $this->service_name,

                'price' =>
                    $this->price_snapshot,

                'duration_minutes' =>
                    $this->duration_minutes_snapshot,
            ],

            'service' =>
                $this->whenLoaded(
                    'service',
                    fn (): array => [
                        'id' =>
                            $this->service->id,

                        'name' =>
                            $this->service->name,

                        'is_active' =>
                            $this->service->is_active,
                    ]
                ),

            'notes' =>
                $this->notes,

            'cancellation_reason' =>
                $this->cancellation_reason,

            'cancelled_by' =>
                $this->cancelled_by,

            'cancelled_at' =>
                $this->cancelled_at
                    ?->toISOString(),

            'completed_at' =>
                $this->completed_at
                    ?->toISOString(),

            'no_show_at' =>
                $this->no_show_at
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