<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\StoreMediaImageRequest;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Service;
use App\Models\Shop;
use App\Services\Media\ShopMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceImageController extends Controller
{
    public function __construct(
        private readonly ShopMediaService $mediaService
    ) {
    }

    /**
     * Upload or replace one owned service image.
     */
    public function store(
        StoreMediaImageRequest $request,
        string $service
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        /*
        |--------------------------------------------------------------------------
        | Tenant Isolation Before Upload
        |--------------------------------------------------------------------------
        |
        | Resolve the service before storing any physical file. Supplying another
        | shop's service ID therefore results in 404 without creating a file.
        |
        */

        $ownedService =
            $this->ownedService(
                $shop,
                $service
            );

        $updatedService =
            $this
                ->mediaService
                ->replaceServiceImage(
                    $shop,
                    $ownedService,
                    $request->file(
                        'image'
                    )
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Service image updated successfully.',

            'data' => [
                'service' =>
                    (
                        new ServiceResource(
                            $updatedService
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    /**
     * Remove one owned service image.
     */
    public function destroy(
        Request $request,
        string $service
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $ownedService =
            $this->ownedService(
                $shop,
                $service
            );

        $updatedService =
            $this
                ->mediaService
                ->deleteServiceImage(
                    $shop,
                    $ownedService
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Service image removed successfully.',

            'data' => [
                'service' =>
                    (
                        new ServiceResource(
                            $updatedService
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request
            ->user()
            ->shop()
            ->firstOrFail();
    }

    /**
     * Resolve only a service belonging to this owner's shop.
     */
    private function ownedService(
        Shop $shop,
        string $service
    ): Service {
        return $shop
            ->services()
            ->whereKey(
                $service
            )
            ->firstOrFail();
    }
}