<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shop
 */
class ShopLocationResource extends JsonResource
{
    /**
     * Transform the shop's map location into a safe API representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'shop_id' => $this->id,

            'shop_name' => $this->name,

            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,

            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'has_location' => $this->hasLocation(),
        ];
    }
}