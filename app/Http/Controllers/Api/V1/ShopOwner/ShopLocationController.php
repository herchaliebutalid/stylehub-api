<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\UpdateShopLocationRequest;
use App\Http\Resources\Api\V1\ShopLocationResource;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopLocationController extends Controller
{
    /**
     * Return the authenticated shop owner's current map location.
     */
    public function show(
        Request $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        return response()->json([
            'success' => true,

            'data' => [
                'location' =>
                    (new ShopLocationResource(
                        $shop
                    ))->resolve($request),
            ],
        ]);
    }

    /**
     * Replace the authenticated shop owner's exact map coordinates.
     */
    public function update(
        UpdateShopLocationRequest $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $validated = $request->validated();

        $updatedShop = DB::transaction(
            function () use (
                $shop,
                $validated
            ): Shop {
                /*
                 * Serialize concurrent location writes for this shop and never
                 * accept ownership identifiers from the request payload.
                 */
                $lockedShop = Shop::query()
                    ->whereKey($shop->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedShop->latitude =
                    (float) $validated['latitude'];

                $lockedShop->longitude =
                    (float) $validated['longitude'];

                $lockedShop->save();

                return $lockedShop->fresh();
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Shop location updated successfully.',

            'data' => [
                'location' =>
                    (new ShopLocationResource(
                        $updatedShop
                    ))->resolve($request),
            ],
        ]);
    }

    /**
     * Resolve only the shop owned by the authenticated account.
     */
    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request->user()
            ->shop()
            ->firstOrFail();
    }
}