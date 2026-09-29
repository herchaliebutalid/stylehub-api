<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Feedback
 */
class CustomerPublicFeedbackResource extends JsonResource
{
    /**
     * Customer-visible review representation.
     *
     * No reviewer account identifiers or contact information
     * are exposed.
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
            | Verification
            |--------------------------------------------------------------------------
            |
            | The query supplying this resource is restricted through
            | Feedback::verified().
            |
            */

            'verified_booking' =>
                true,

            'reviewer' => [
                'label' =>
                    'Verified Customer',
            ],

            /*
            |--------------------------------------------------------------------------
            | Appointment Snapshot
            |--------------------------------------------------------------------------
            |
            | Only safe appointment information is shown.
            |
            */

            'service' =>
                $this->whenLoaded(
                    'appointment',
                    fn (): array => [
                        'name' =>
                            $this->appointment
                                ->service_name,

                        'appointment_date' =>
                            $this->appointment
                                ->appointment_date
                                ->toDateString(),
                    ]
                ),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),
        ];
    }
}