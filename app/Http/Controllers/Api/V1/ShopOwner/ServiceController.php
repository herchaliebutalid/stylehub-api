<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\StoreServiceRequest;
use App\Http\Requests\Api\V1\ShopOwner\UpdateServiceRequest;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceController extends Controller
{
    /**
     * List services belonging only to the authenticated owner's shop.
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $this->authenticatedShop(
            $request
        );

        $services = $shop->services()
            ->latest()
            ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'services' =>
                    ServiceResource::collection(
                        $services
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Create a service under the authenticated owner's shop.
     */
    public function store(
        StoreServiceRequest $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $validated = $request->validated();

        /*
         * shop_id comes from the authenticated relationship,
         * never from client input.
         */
        $service = $shop->services()
            ->make($validated);

        $service->save();

        return response()->json([
            'success' => true,

            'message' =>
                'Service created successfully.',

            'data' => [
                'service' => (
                    new ServiceResource(
                        $service
                    )
                )->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Show one service belonging to the authenticated owner's shop.
     */
    public function show(
        Request $request,
        string $service
    ): JsonResponse {
        $ownedService = $this->ownedService(
            $request,
            $service
        );

        return response()->json([
            'success' => true,

            'data' => [
                'service' => (
                    new ServiceResource(
                        $ownedService
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Update one service belonging to the authenticated owner's shop.
     */
    public function update(
        UpdateServiceRequest $request,
        string $service
    ): JsonResponse {
        $ownedService = $this->ownedService(
            $request,
            $service
        );

        $ownedService->fill(
            $request->validated()
        );

        $ownedService->save();

        return response()->json([
            'success' => true,

            'message' =>
                'Service updated successfully.',

            'data' => [
                'service' => (
                    new ServiceResource(
                        $ownedService
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Soft-delete one service belonging to the authenticated owner's shop.
     */
    public function destroy(
        Request $request,
        string $service
    ): JsonResponse {
        $ownedService = $this->ownedService(
            $request,
            $service
        );

        $ownedService->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Service deleted successfully.',
        ]);
    }

    /**
     * Resolve the authenticated owner's approved shop.
     */
    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request->user()
            ->shop()
            ->firstOrFail();
    }

    /**
     * Resolve a service strictly through the authenticated owner's shop.
     *
     * This is the core tenant-isolation rule. A service belonging to a
     * different shop is treated as not found rather than revealing that
     * another tenant owns the supplied ID.
     */
    private function ownedService(
        Request $request,
        string $service
    ): Service {
        $shop = $this->authenticatedShop(
            $request
        );

        return $shop->services()
            ->whereKey($service)
            ->firstOrFail();
    }
}