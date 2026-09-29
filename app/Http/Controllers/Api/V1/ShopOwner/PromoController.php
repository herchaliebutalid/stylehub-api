<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\StorePromoRequest;
use App\Http\Requests\Api\V1\ShopOwner\UpdatePromoRequest;
use App\Http\Resources\Api\V1\PromoResource;
use App\Models\Promo;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PromoController extends Controller
{
    /**
     * List only promotions owned by the authenticated owner's shop.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $promos =
            $shop->promos()
                ->with([
                    'service',
                ])
                ->orderByDesc(
                    'created_at'
                )
                ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'promos' =>
                    PromoResource::collection(
                        $promos
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Create a promotion for the authenticated owner's shop.
     */
    public function store(
        StorePromoRequest $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $validated =
            $request->validated();

        $promo =
            DB::transaction(
                function () use (
                    $shop,
                    $validated
                ): Promo {
                    $targetServiceId =
                        $this->resolveTargetServiceId(
                            $shop,
                            $validated[
                                'service_id'
                            ]
                        );

                    $promo =
                        new Promo([
                            'title' =>
                                $validated['title'],

                            'description' =>
                                $validated[
                                    'description'
                                ]
                                ?? null,

                            'discount_type' =>
                                $validated[
                                    'discount_type'
                                ],

                            'discount_value' =>
                                $validated[
                                    'discount_value'
                                ],

                            'starts_at' =>
                                $validated[
                                    'starts_at'
                                ],

                            'ends_at' =>
                                $validated[
                                    'ends_at'
                                ],

                            'is_active' =>
                                $validated[
                                    'is_active'
                                ],
                        ]);

                    /*
                     * Ownership is assigned exclusively by
                     * authenticated backend context.
                     */
                    $promo->shop_id =
                        $shop->id;

                    $promo->service_id =
                        $targetServiceId;

                    $promo->save();

                    return $promo->load([
                        'service',
                    ]);
                }
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Promotion created successfully.',

            'data' => [
                'promo' => (
                    new PromoResource(
                        $promo
                    )
                )->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Show one promotion belonging only to this shop.
     */
    public function show(
        Request $request,
        string $promo
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $ownedPromo =
            $shop->promos()
                ->with([
                    'service',
                ])
                ->whereKey(
                    $promo
                )
                ->firstOrFail();

        return response()->json([
            'success' => true,

            'data' => [
                'promo' => (
                    new PromoResource(
                        $ownedPromo
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Update one promotion strictly inside the owner's shop.
     */
    public function update(
        UpdatePromoRequest $request,
        string $promo
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $validated =
            $request->validated();

        $updatedPromo =
            DB::transaction(
                function () use (
                    $shop,
                    $promo,
                    $validated
                ): Promo {
                    /*
                     * Cross-tenant IDs return 404 because we query
                     * strictly through the authenticated shop.
                     */
                    $ownedPromo =
                        $shop->promos()
                            ->whereKey(
                                $promo
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $targetServiceId =
                        $this->resolveTargetServiceId(
                            $shop,
                            $validated[
                                'service_id'
                            ]
                        );

                    $ownedPromo->fill([
                        'title' =>
                            $validated['title'],

                        'description' =>
                            $validated[
                                'description'
                            ],

                        'discount_type' =>
                            $validated[
                                'discount_type'
                            ],

                        'discount_value' =>
                            $validated[
                                'discount_value'
                            ],

                        'starts_at' =>
                            $validated[
                                'starts_at'
                            ],

                        'ends_at' =>
                            $validated[
                                'ends_at'
                            ],

                        'is_active' =>
                            $validated[
                                'is_active'
                            ],
                    ]);

                    /*
                     * service_id remains protected from ordinary
                     * mass assignment.
                     */
                    $ownedPromo->service_id =
                        $targetServiceId;

                    $ownedPromo->save();

                    return $ownedPromo->load([
                        'service',
                    ]);
                }
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Promotion updated successfully.',

            'data' => [
                'promo' => (
                    new PromoResource(
                        $updatedPromo
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Soft-delete one promotion belonging only to this shop.
     */
    public function destroy(
        Request $request,
        string $promo
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        DB::transaction(
            function () use (
                $shop,
                $promo
            ): void {
                $ownedPromo =
                    $shop->promos()
                        ->whereKey(
                            $promo
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $ownedPromo->delete();
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Promotion deleted successfully.',
        ]);
    }

    /**
     * Resolve an optional service strictly inside this shop.
     *
     * null means the promotion is shop-wide.
     */
    private function resolveTargetServiceId(
        Shop $shop,
        mixed $serviceId
    ): ?int {
        if ($serviceId === null) {
            return null;
        }

        $service =
            $shop->services()
                ->whereKey(
                    (int) $serviceId
                )
                ->first();

        if ($service === null) {
            throw ValidationException::withMessages([
                'service_id' =>
                    'The selected service does not belong to this shop.',
            ]);
        }

        return $service->id;
    }

    /**
     * Resolve the authenticated shop owner's approved shop.
     */
    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request->user()
            ->shop()
            ->firstOrFail();
    }
}