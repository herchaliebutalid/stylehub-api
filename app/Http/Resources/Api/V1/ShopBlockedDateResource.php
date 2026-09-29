<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ShopBlockedDate
 */
class ShopBlockedDateResource extends JsonResource
{
    /**
     * Return only Shop Owner-safe blocked-date information.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'blocked_date' =>
                $this->blocked_date
                    ->format('Y-m-d'),

            'reason' =>
                $this->reason,

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}