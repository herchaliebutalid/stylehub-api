<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the user into a safe API representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'first_name' =>
                $this->first_name,

            'middle_name' =>
                $this->middle_name,

            'last_name' =>
                $this->last_name,

            'full_name' =>
                $this->full_name,

            'email' =>
                $this->email,

            'phone_number' =>
                $this->phone_number,

            /*
            |--------------------------------------------------------------------------
            | Phone Verification
            |--------------------------------------------------------------------------
            |
            | Expose only a boolean to the mobile application.
            |
            | The raw verification timestamp is not needed by the client.
            |
            */

            'phone_verified' =>
                $this->phone_verified_at
                    !== null,

            /*
            |--------------------------------------------------------------------------
            | Profile Photo
            |--------------------------------------------------------------------------
            */

            'profile_photo_url' =>
                filled(
                    $this->profile_photo_path
                )
                    ? '/storage/'
                        .ltrim(
                            $this->profile_photo_path,
                            '/'
                        )
                    : null,

            'role' =>
                $this->role->value,

            'role_label' =>
                $this->role->label(),

            'status' =>
                $this->status->value,

            'status_label' =>
                $this->status->label(),

            'email_verified' =>
                $this->email_verified_at
                    !== null,

            'last_login_at' =>
                $this
                    ->last_login_at
                    ?->toISOString(),

            'created_at' =>
                $this
                    ->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this
                    ->updated_at
                    ?->toISOString(),
        ];
    }
}