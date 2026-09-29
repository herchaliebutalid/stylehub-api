<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shop
 */
class AdminShopReviewResource extends JsonResource
{
    /**
     * Transform shop registration data for administrator review.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'type' => $this->type->value,
            'type_label' => $this->type->label(),

            'description' => $this->description,

            'phone_number' => $this->phone_number,
            'email' => $this->email,

            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,

            'availability_status' =>
                $this->availability_status->value,

            'availability_status_label' =>
                $this->availability_status->label(),

            'approval_status' =>
                $this->approval_status->value,

            'approval_status_label' =>
                $this->approval_status->label(),

            'rejection_reason' =>
                $this->rejection_reason,

            'reviewed_at' =>
                $this->reviewed_at?->toISOString(),

            'owner' => $this->whenLoaded(
                'owner',
                fn (): array => [
                    'id' => $this->owner->id,
                    'full_name' => $this->owner->full_name,
                    'email' => $this->owner->email,
                    'phone_number' =>
                        $this->owner->phone_number,
                ]
            ),

            'reviewer' => $this->whenLoaded(
                'reviewer',
                fn (): array => [
                    'id' => $this->reviewer->id,
                    'full_name' =>
                        $this->reviewer->full_name,
                ]
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}