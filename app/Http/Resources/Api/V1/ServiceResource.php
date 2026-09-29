<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Service
 */
class ServiceResource extends JsonResource
{
    /**
     * Transform a service into a safe Shop Owner API representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $imageUrl =
            null;

        if (
            $this->hasImage()
        ) {
            $imageUrl =
                Storage::disk(
                    (string) config(
                        'shop-media.disk',
                        'public'
                    )
                )->url(
                    $this->image_path
                );
        }

        return [
            'id' =>
                $this->id,

            'name' =>
                $this->name,

            'description' =>
                $this->description,

            'price' =>
                $this->price,

            'duration_minutes' =>
                $this->duration_minutes,

            'is_active' =>
                $this->is_active,

            /*
            |--------------------------------------------------------------------------
            | Service Display Image
            |--------------------------------------------------------------------------
            |
            | image_path stays private to Laravel.
            |
            */

            'image' => [
                'has_image' =>
                    $this->hasImage(),

                'image_url' =>
                    $imageUrl,
            ],

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}