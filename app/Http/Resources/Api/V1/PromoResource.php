<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Promo
 */
class PromoResource extends JsonResource
{
    /**
     * Shop-owner promotion representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'scope' =>
                $this->isShopWide()
                    ? 'shop_wide'
                    : 'service_specific',

            'title' =>
                $this->title,

            'description' =>
                $this->description,

            'discount' => [
                'type' =>
                    $this->discount_type
                        ->value,

                'type_label' =>
                    $this->discount_type
                        ->label(),

                'value' =>
                    $this->discount_value,
            ],

            'starts_at' =>
                $this->starts_at
                    ?->toISOString(),

            'ends_at' =>
                $this->ends_at
                    ?->toISOString(),

            'is_active' =>
                $this->is_active,

            'is_currently_active' =>
                $this->isCurrentlyActive(),

            'is_upcoming' =>
                $this->isUpcoming(),

            'has_ended' =>
                $this->hasEnded(),

            /*
            |--------------------------------------------------------------------------
            | Optional Service
            |--------------------------------------------------------------------------
            */

            'service' =>
                $this->whenLoaded(
                    'service',
                    fn (): ?array =>
                        $this->service === null
                            ? null
                            : [
                                'id' =>
                                    $this->service->id,

                                'name' =>
                                    $this->service->name,

                                'is_active' =>
                                    $this->service
                                        ->is_active,
                            ]
                ),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}