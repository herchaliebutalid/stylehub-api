<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Feedback
 */
class CustomerFeedbackResource extends JsonResource
{
    /**
     * Customer-safe feedback representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'rating' =>
                $this->rating,

            'comment' =>
                $this->comment,

            /*
            |--------------------------------------------------------------------------
            | Appointment
            |--------------------------------------------------------------------------
            */

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
                    ]
                ),

            /*
            |--------------------------------------------------------------------------
            | Reviewed Shop
            |--------------------------------------------------------------------------
            */

            'shop' =>
                $this->whenLoaded(
                    'shop',
                    fn (): array => [
                        'id' =>
                            $this->shop->id,

                        'name' =>
                            $this->shop->name,

                        'type' =>
                            $this->shop
                                ->type
                                ->value,

                        'type_label' =>
                            $this->shop
                                ->type
                                ->label(),
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