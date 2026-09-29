<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ShopSchedule
 */
class ShopScheduleResource extends JsonResource
{
    /**
     * Transform a weekly schedule entry.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'day_of_week' =>
                $this->day_of_week->value,

            'day_label' =>
                $this->day_of_week->label(),

            'is_open' =>
                $this->is_open,

            'opens_at' =>
                $this->opens_at,

            'closes_at' =>
                $this->closes_at,

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}