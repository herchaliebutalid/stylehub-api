<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\ReorderShopGalleryRequest;
use App\Http\Requests\Api\V1\ShopOwner\StoreMediaImageRequest;
use App\Http\Resources\Api\V1\ShopGalleryImageResource;
use App\Models\Shop;
use App\Services\Media\ShopMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopGalleryController extends Controller
{
    public function __construct(
        private readonly ShopMediaService $mediaService
    ) {
    }

    /**
     * List the authenticated owner's shop gallery.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $images =
            $this
                ->mediaService
                ->galleryImages(
                    $this->authenticatedShop(
                        $request
                    )
                );

        return response()->json([
            'success' =>
                true,

            'data' => [
                'images' =>
                    ShopGalleryImageResource::collection(
                        $images
                    )->resolve(
                        $request
                    ),

                'max_images' =>
                    (int) config(
                        'shop-media.gallery.max_images',
                        10
                    ),
            ],
        ]);
    }

    /**
     * Upload one gallery image.
     */
    public function store(
        StoreMediaImageRequest $request
    ): JsonResponse {
        $galleryImage =
            $this
                ->mediaService
                ->addGalleryImage(
                    $this->authenticatedShop(
                        $request
                    ),

                    $request->file(
                        'image'
                    )
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Gallery image uploaded successfully.',

            'data' => [
                'image' =>
                    (
                        new ShopGalleryImageResource(
                            $galleryImage
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Replace the complete gallery ordering.
     */
    public function reorder(
        ReorderShopGalleryRequest $request
    ): JsonResponse {
        $images =
            $this
                ->mediaService
                ->reorderGallery(
                    $this->authenticatedShop(
                        $request
                    ),

                    $request->validated(
                        'image_ids'
                    )
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Gallery order updated successfully.',

            'data' => [
                'images' =>
                    ShopGalleryImageResource::collection(
                        $images
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    /**
     * Delete one owned gallery image.
     */
    public function destroy(
        Request $request,
        string $galleryImage
    ): JsonResponse {
        $this
            ->mediaService
            ->deleteGalleryImage(
                $this->authenticatedShop(
                    $request
                ),

                (int) $galleryImage
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Gallery image removed successfully.',
        ]);
    }

    /**
     * Resolve the authenticated owner's approved shop.
     */
    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request
            ->user()
            ->shop()
            ->firstOrFail();
    }
}