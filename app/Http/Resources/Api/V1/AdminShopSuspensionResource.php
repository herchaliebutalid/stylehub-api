<?php

namespace App\Http\Resources\Api\V1;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shop
 */
class AdminShopSuspensionResource extends JsonResource
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

        return [
            'id' =>
                $this->id,

            'name' =>
                $this->name,

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

            'suspension' => [
                'suspended_by' =>
                    $this->suspended_by,

                'suspended_at' =>
                    $this->dateTimeValue(
                        $this->suspended_at
                    ),

                'reason' =>
                    $this->suspension_reason,
            ],

            'owner' =>
                $owner === null
                    ? null
                    : [
                        'id' =>
                            $owner->id,

                        'status' =>
                            $this->enumValue(
                                $owner->status
                            ),
                    ],
        ];
    }

    private function enumValue(
        mixed $value
    ): mixed {
        return $value instanceof BackedEnum
            ? $value->value
            : $value;
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