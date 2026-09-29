<?php

namespace App\Http\Resources\Api\V1;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $shop =
            $this->relationLoaded(
                'shop'
            )
                ? $this->shop
                : null;

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
                trim(
                    implode(
                        ' ',
                        array_filter([
                            $this->first_name,
                            $this->middle_name,
                            $this->last_name,
                        ])
                    )
                ),

            'email' =>
                $this->email,

            'phone_number' =>
                $this->phone_number,

            'role' =>
                $this->enumValue(
                    $this->role
                ),

            'status' =>
                $this->enumValue(
                    $this->status
                ),

            'email_verified' =>
                $this->email_verified_at
                !== null,

            'last_login_at' =>
                $this->dateTimeValue(
                    $this->last_login_at
                ),

            /*
            |--------------------------------------------------------------------------
            | Customer Activity
            |--------------------------------------------------------------------------
            */

            'appointments_count' =>
                isset(
                    $this->appointments_count
                )
                    ? (int) $this->appointments_count
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Shop Owner Shop Summary
            |--------------------------------------------------------------------------
            */

            'shop' =>
                $shop === null
                    ? null
                    : [
                        'id' =>
                            $shop->id,

                        'name' =>
                            $shop->name,

                        'approval_status' =>
                            $this->enumValue(
                                $shop->approval_status
                            ),

                        'availability_status' =>
                            $this->enumValue(
                                $shop->availability_status
                            ),
                    ],

            'created_at' =>
                $this->dateTimeValue(
                    $this->created_at
                ),

            'updated_at' =>
                $this->dateTimeValue(
                    $this->updated_at
                ),
        ];
    }

    private function enumValue(
        mixed $value
    ): mixed {
        if (
            $value instanceof BackedEnum
        ) {
            return $value->value;
        }

        return $value;
    }

    private function dateTimeValue(
        mixed $value
    ): ?string {
        if (
            $value instanceof DateTimeInterface
        ) {
            return $value->format(
                DATE_ATOM
            );
        }

        if (
            is_string(
                $value
            )
        ) {
            return $value;
        }

        return null;
    }
}