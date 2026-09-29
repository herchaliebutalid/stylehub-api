<?php

namespace App\Http\Resources\Api\V1;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shop
 */
class AdminShopResource extends JsonResource
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

            'name' =>
                $this->name,

            'type' =>
                $this->enumValue(
                    $this->type
                ),

            'description' =>
                $this->description,

            /*
            |--------------------------------------------------------------------------
            | Shop Contact Information
            |--------------------------------------------------------------------------
            |
            | Administrator-only.
            |
            */

            'phone_number' =>
                $this->phone_number,

            'email' =>
                $this->email,

            /*
            |--------------------------------------------------------------------------
            | Location
            |--------------------------------------------------------------------------
            */

            'address' =>
                $this->address,

            'city' =>
                $this->city,

            'province' =>
                $this->province,

            /*
            |--------------------------------------------------------------------------
            | Current State
            |--------------------------------------------------------------------------
            */

            'approval_status' =>
                $this->enumValue(
                    $this->approval_status
                ),

            'availability_status' =>
                $this->enumValue(
                    $this->availability_status
                ),

            'administrative_status' =>
                $this->enumValue(
                    $this->administrative_status
                ),

            /*
            |--------------------------------------------------------------------------
            | Independent Suspension Summary
            |--------------------------------------------------------------------------
            |
            | Keep an explicit, stable object shape even while the shop is active.
            |
            */

            'suspension' => [
                'is_suspended' =>
                    $this->isSuspended(),

                'suspended_at' =>
                    $this->dateTimeValue(
                        $this->suspended_at
                    ),

                'reason' =>
                    $this->suspension_reason,
            ],

            /*
            |--------------------------------------------------------------------------
            | Owner Summary
            |--------------------------------------------------------------------------
            */

            'owner' =>
                $this->ownerData(),

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

    /**
     * @return array<string, mixed>|null
     */
    private function ownerData(): ?array
    {
        if (
            ! $this->relationLoaded(
                'owner'
            )
            || $this->owner === null
        ) {
            return null;
        }

        $owner =
            $this->owner;

        $fullName =
            trim(
                implode(
                    ' ',
                    array_filter([
                        $owner->first_name,
                        $owner->middle_name,
                        $owner->last_name,
                    ])
                )
            );

        return [
            'id' =>
                $owner->id,

            'full_name' =>
                $fullName,

            'email' =>
                $owner->email,

            'phone_number' =>
                $owner->phone_number,

            'role' =>
                $this->enumValue(
                    $owner->role
                ),

            'status' =>
                $this->enumValue(
                    $owner->status
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
            return $value
                ->format(
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