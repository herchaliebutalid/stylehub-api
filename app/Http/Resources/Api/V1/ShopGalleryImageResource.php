<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\ShopGalleryImage
 */
class ShopGalleryImageResource extends JsonResource
{
    /**
     * Return only media information safe for the Shop Owner app.
     *
     * The internal storage path is deliberately not exposed.
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

            'created_at' =>
                $this->created_at
                    ?->toISOString(),
        ];
    }
}