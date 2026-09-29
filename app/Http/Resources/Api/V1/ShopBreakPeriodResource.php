<?php

namespace App\Http\Resources\Api\V1;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ShopBreakPeriod
 */
class ShopBreakPeriodResource extends JsonResource
{
    /**
     * Return only Shop Owner-safe break-period information.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $day = $this->day_of_week;

        return [
            'id' =>
                $this->id,

            'day_of_week' =>
                $day instanceof BackedEnum
                    ? $day->value
                    : (int) $day,

            'day_label' =>
                $day instanceof \App\Enums\DayOfWeek
                    ? $day->label()
                    : '',

            'starts_at' =>
                substr(
                    (string) $this->starts_at,
                    0,
                    5
                ),

            'ends_at' =>
                substr(
                    (string) $this->ends_at,
                    0,
                    5
                ),

            'label' =>
                $this->label,

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}