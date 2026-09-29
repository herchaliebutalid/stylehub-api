<?php

namespace App\Http\Resources\Api\V1;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shop
 */
class AdminShopDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $owner =
            $this->relationLoaded(
                'owner'
            )
                ? $this->owner
                : null;

        $reviewer =
            $this->relationLoaded(
                'reviewer'
            )
                ? $this->reviewer
                : null;

        $suspender =
            $this->relationLoaded(
                'suspender'
            )
                ? $this->suspender
                : null;

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
            | Shop Contact
            |--------------------------------------------------------------------------
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
            | Current Shop State
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
            | Independent Administrative Suspension
            |--------------------------------------------------------------------------
            |
            | This is deliberately separate from owner.status, approval_status,
            | and availability_status.
            |
            */

            'suspension' => [
                'is_suspended' =>
                    $this->isSuspended(),

                'suspended_by' =>
                    $this->suspended_by,

                'suspender' =>
                    $suspender === null
                        ? null
                        : [
                            'id' =>
                                $suspender->id,

                            'full_name' =>
                                trim(
                                    implode(
                                        ' ',
                                        array_filter([
                                            $suspender->first_name,
                                            $suspender->middle_name,
                                            $suspender->last_name,
                                        ])
                                    )
                                ),
                        ],

                'suspended_at' =>
                    $this->dateTimeValue(
                        $this->suspended_at
                    ),

                'reason' =>
                    $this->suspension_reason,
            ],

            /*
            |--------------------------------------------------------------------------
            | Shop Owner
            |--------------------------------------------------------------------------
            |
            | Administrator web only.
            |
            | Passwords, tokens and remember_token are never exposed.
            |
            */

            'owner' =>
                $owner === null
                    ? null
                    : [
                        'id' =>
                            $owner->id,

                        'first_name' =>
                            $owner->first_name,

                        'middle_name' =>
                            $owner->middle_name,

                        'last_name' =>
                            $owner->last_name,

                        'full_name' =>
                            trim(
                                implode(
                                    ' ',
                                    array_filter([
                                        $owner->first_name,
                                        $owner->middle_name,
                                        $owner->last_name,
                                    ])
                                )
                            ),

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

                        'last_login_at' =>
                            $this->dateTimeValue(
                                $owner->last_login_at
                            ),

                        'created_at' =>
                            $this->dateTimeValue(
                                $owner->created_at
                            ),
                    ],

            /*
            |--------------------------------------------------------------------------
            | Administrative Review Information
            |--------------------------------------------------------------------------
            */

            'review' => [
                'reviewed_by' =>
                    $this->reviewed_by,

                'reviewer' =>
                    $reviewer === null
                        ? null
                        : [
                            'id' =>
                                $reviewer->id,

                            'full_name' =>
                                trim(
                                    implode(
                                        ' ',
                                        array_filter([
                                            $reviewer->first_name,
                                            $reviewer->middle_name,
                                            $reviewer->last_name,
                                        ])
                                    )
                                ),
                        ],

                'reviewed_at' =>
                    $this->dateTimeValue(
                        $this->reviewed_at
                    ),

                'rejection_reason' =>
                    $this->rejection_reason,
            ],

            /*
            |--------------------------------------------------------------------------
            | Operational Statistics
            |--------------------------------------------------------------------------
            */

            'statistics' =>
                $this->admin_statistics
                ?? [
                    'services' =>
                        0,

                    'active_services' =>
                        0,

                    'appointments' =>
                        0,

                    'feedback' =>
                        0,

                    'promos' =>
                        0,

                    'active_promos' =>
                        0,
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