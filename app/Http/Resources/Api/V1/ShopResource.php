<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shop
 */
class ShopResource extends JsonResource
{
    /**
     * Transform a shop into a safe API representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'name' =>
                $this->name,

            'type' =>
                $this->type->value,

            'type_label' =>
                $this->type->label(),

            'description' =>
                $this->description,

            'phone_number' =>
                $this->phone_number,

            'email' =>
                $this->email,

            'address' =>
                $this->address,

            'city' =>
                $this->city,

            'province' =>
                $this->province,

            'availability_status' =>
                $this->availability_status
                    ->value,

            'availability_status_label' =>
                $this->availability_status
                    ->label(),

            'approval_status' =>
                $this->approval_status
                    ->value,

            'approval_status_label' =>
                $this->approval_status
                    ->label(),

            /*
            |--------------------------------------------------------------------------
            | Independent Administrative Shop State
            |--------------------------------------------------------------------------
            |
            | /api/v1/auth/me uses this resource for a Shop Owner.
            |
            | This allows the mobile application to distinguish:
            |
            | approved + active
            | approved + suspended
            | pending
            | rejected
            |
            */

            'administrative_status' =>
                $this->administrative_status
                    ->value,

            'administrative_status_label' =>
                $this->administrative_status
                    ->label(),

            'suspension' => [
                'is_suspended' =>
                    $this->isSuspended(),

                'suspended_at' =>
                    $this->suspended_at
                        ?->toISOString(),

                'reason' =>
                    $this->suspension_reason,
            ],

            'rejection_reason' =>
                $this->rejection_reason,

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