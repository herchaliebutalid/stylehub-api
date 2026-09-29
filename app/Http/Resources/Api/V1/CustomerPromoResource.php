<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Promo
 */
class CustomerPromoResource extends JsonResource
{
    /**
     * Customer-safe promotion representation.
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

            /*
            |--------------------------------------------------------------------------
            | Shop
            |--------------------------------------------------------------------------
            */

            'shop' =>
                $this->whenLoaded(
                    'shop',
                    fn (): array => [
                        'id' =>
                            $this->shop->id,

                        'name' =>
                            $this->shop->name,

                        'type' =>
                            $this->shop
                                ->type
                                ->value,

                        'type_label' =>
                            $this->shop
                                ->type
                                ->label(),

                        'city' =>
                            $this->shop->city,

                        'province' =>
                            $this->shop
                                ->province,
                    ]
                ),

            /*
            |--------------------------------------------------------------------------
            | Optional Target Service
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
                                    $this->service
                                        ->name,

                                'price' =>
                                    $this->service
                                        ->price,

                                'duration_minutes' =>
                                    $this->service
                                        ->duration_minutes,
                            ]
                ),
        ];
    }
}