<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Shop
 */
class CustomerShopResource extends JsonResource
{
    /**
     * Customer-safe shop representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Public Shop Owner Profile Photo
        |--------------------------------------------------------------------------
        |
        | Customers are allowed to see only the profile picture associated
        | with the owner of this shop.
        |
        | We deliberately do NOT expose:
        |
        | - owner ID
        | - owner name
        | - owner email
        | - owner phone number
        | - account status
        | - authentication information
        |
        */

        $ownerProfilePhotoUrl =
            null;

        if (
            $this->relationLoaded(
                'owner'
            )
            &&
            $this->owner !== null
            &&
            is_string(
                $this->owner
                    ->profile_photo_path
            )
            &&
            trim(
                $this->owner
                    ->profile_photo_path
            ) !== ''
        ) {
            $ownerProfilePhotoUrl =
                Storage::disk(
                    'public'
                )->url(
                    $this->owner
                        ->profile_photo_path
                );
        }


        return [
            /*
            |--------------------------------------------------------------------------
            | Shop
            |--------------------------------------------------------------------------
            */

            'id' =>
                $this->id,

            'name' =>
                $this->name,

            'type' =>
                $this->type->value,

            'type_label' =>
                $this->type->label(),

            'description' =>
                $this->description,


            /*
            |--------------------------------------------------------------------------
            | Shop Owner Public Profile
            |--------------------------------------------------------------------------
            |
            | ShopOwnerAvatar.tsx reads:
            |
            | shop.owner.profile_photo_url
            |
            | If this value is null, the mobile UI automatically displays
            | the barber-shop / beauty-parlor fallback icon.
            |
            */

            'owner' => [
                'profile_photo_url' =>
                    $ownerProfilePhotoUrl,
            ],


            /*
            |--------------------------------------------------------------------------
            | Shop Contact Information
            |--------------------------------------------------------------------------
            */

            'phone_number' =>
                $this->phone_number,

            'email' =>
                $this->email,

            'address' =>
                $this->address,

            'city' =>
                $this->city,

            'province' =>
                $this->province,


            /*
            |--------------------------------------------------------------------------
            | Public Map Location
            |--------------------------------------------------------------------------
            |
            | Exact shop coordinates are customer-visible only through this
            | customer-safe resource. Internal ownership, approval and review
            | fields remain excluded. Existing shops without a pin return a complete
            | null coordinate pair and has_location=false.
            |
            */

            'location' => [
                'latitude' =>
                    $this->latitude,

                'longitude' =>
                    $this->longitude,

                'has_location' =>
                    $this->hasLocation(),
            ],


            /*
            |--------------------------------------------------------------------------
            | Availability
            |--------------------------------------------------------------------------
            */

            'availability' => [
                'status' =>
                    $this
                        ->availability_status
                        ->value,

                'status_label' =>
                    $this
                        ->availability_status
                        ->label(),

                'accepts_appointments' =>
                    $this
                        ->availability_status
                        ->acceptsAppointments(),
            ],


            /*
            |--------------------------------------------------------------------------
            | Rating Summary
            |--------------------------------------------------------------------------
            */

            'rating' => [
                'average' =>
                    isset(
                        $this->feedback_avg_rating
                    )
                        ? round(
                            (float) $this
                                ->feedback_avg_rating,
                            2
                        )
                        : null,

                'review_count' =>
                    isset(
                        $this->feedback_count
                    )
                        ? (int) $this
                            ->feedback_count
                        : 0,
            ],


            /*
            |--------------------------------------------------------------------------
            | Active Service Count
            |--------------------------------------------------------------------------
            */

            'active_services_count' =>
                $this->when(
                    isset(
                        $this
                            ->active_services_count
                    ),

                    fn (): int =>
                        (int) $this
                            ->active_services_count
                ),


            /*
            |--------------------------------------------------------------------------
            | Shop Gallery
            |--------------------------------------------------------------------------
            |
            | Gallery data is included only when the controller explicitly loads the
            | relationship. Shop-list responses therefore stay lightweight, while the
            | detail response can expose the ordered customer-safe gallery.
            |
            */

            'gallery' =>
                $this->when(
                    $this->relationLoaded(
                        'galleryImages'
                    ),

                    fn (): array =>
                        CustomerShopGalleryImageResource::collection(
                            $this->galleryImages
                        )->resolve(
                            $request
                        )
                ),


            /*
            |--------------------------------------------------------------------------
            | Active Services
            |--------------------------------------------------------------------------
            */

            'services' =>
                $this->when(
                    $this->relationLoaded(
                        'services'
                    ),

                    fn (): array =>
                        CustomerServiceResource::collection(
                            $this->services
                        )->resolve(
                            $request
                        )
                ),


            /*
            |--------------------------------------------------------------------------
            | Weekly Schedule
            |--------------------------------------------------------------------------
            */

            'schedule' =>
                $this->when(
                    $this->relationLoaded(
                        'schedules'
                    ),

                    fn (): array =>
                        CustomerShopScheduleResource::collection(
                            $this->schedules
                        )->resolve(
                            $request
                        )
                ),


            /*
            |--------------------------------------------------------------------------
            | Current Promotions
            |--------------------------------------------------------------------------
            */

            'promos' =>
                $this->when(
                    $this->relationLoaded(
                        'promos'
                    ),

                    fn (): array =>
                        CustomerPromoResource::collection(
                            $this->promos
                        )->resolve(
                            $request
                        )
                ),


            /*
            |--------------------------------------------------------------------------
            | Recent Verified Reviews
            |--------------------------------------------------------------------------
            |
            | Only returned on shop-detail requests where the relation
            | was explicitly loaded.
            |
            */

            'recent_reviews' =>
                $this->when(
                    $this->relationLoaded(
                        'feedback'
                    ),

                    fn (): array =>
                        CustomerPublicFeedbackResource::collection(
                            $this->feedback
                        )->resolve(
                            $request
                        )
                ),
        ];
    }
}