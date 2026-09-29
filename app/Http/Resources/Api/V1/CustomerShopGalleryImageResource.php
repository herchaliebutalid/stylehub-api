<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\ShopGalleryImage
 */
class CustomerShopGalleryImageResource extends JsonResource
{
    /**
     * Customer-safe gallery image representation.
     *
     * The internal filesystem path is never exposed to the mobile client.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'image_url' =>
                Storage::disk(
                    (string) config(
                        'shop-media.disk',
                        'public'
                    )
                )->url(
                    $this->image_path
                ),

            'position' =>
                $this->position,
        ];
    }
}