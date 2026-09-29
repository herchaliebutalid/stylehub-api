<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Enums\ShopType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\UpdateShopProfileRequest;
use App\Http\Resources\Api\V1\ShopResource;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopProfileController extends Controller
{
    /**
     * Return the authenticated shop owner's own shop profile.
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
                'shop' =>
                    (new ShopResource(
                        $shop
                    ))->resolve($request),
            ],
        ]);
    }

    /**
     * Replace the editable profile fields of the authenticated owner's shop.
     */
    public function update(
        UpdateShopProfileRequest $request
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
                 * Never trust a shop identifier supplied by the client.
                 * Lock and update only the shop resolved from the authenticated
                 * user's ownership relationship.
                 */
                $lockedShop = Shop::query()
                    ->whereKey($shop->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedShop->name =
                    $validated['name'];

                $lockedShop->type =
                    ShopType::from(
                        $validated['type']
                    );

                $lockedShop->description =
                    $validated['description'];

                $lockedShop->phone_number =
                    $validated['phone_number'];

                $lockedShop->email =
                    $validated['email'];

                $lockedShop->address =
                    $validated['address'];

                $lockedShop->city =
                    $validated['city'];

                $lockedShop->province =
                    $validated['province'];

                /*
                 * Approval, availability, ownership, review metadata, map
                 * coordinates, and all other server-controlled fields are
                 * intentionally untouched.
                 */
                $lockedShop->save();

                return $lockedShop->fresh();
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Shop information updated successfully.',

            'data' => [
                'shop' =>
                    (new ShopResource(
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