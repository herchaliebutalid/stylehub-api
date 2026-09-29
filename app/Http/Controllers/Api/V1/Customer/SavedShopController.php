<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CustomerShopResource;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavedShopController extends Controller
{
    /**
     * List shops saved by the authenticated customer.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $customerId =
            (int) $request
                ->user()
                ->getAuthIdentifier();

        /*
        |--------------------------------------------------------------------------
        | Customer-Visible Saved Shops
        |--------------------------------------------------------------------------
        |
        | A saved shop still has to pass StyleHub's normal customer visibility
        | rules. This prevents pending, rejected, deleted, or unavailable-owner
        | shops from leaking through the saved list.
        |
        */

        $shops =
            Shop::query()
                ->visibleToCustomers()
                ->join(
                    'customer_saved_shops as saved_shops',
                    'saved_shops.shop_id',
                    '=',
                    'shops.id'
                )
                ->where(
                    'saved_shops.customer_id',
                    $customerId
                )
                ->select(
                    'shops.*'
                )
                ->withCount([
                    'services as active_services_count' =>
                        function (
                            Builder $query
                        ): void {
                            $query->where(
                                'is_active',
                                true
                            );
                        },

                    'feedback' =>
                        function (
                            $query
                        ): void {
                            $query->verified();
                        },
                ])
                ->withAvg(
                    [
                        'feedback' =>
                            function (
                                $query
                            ): void {
                                $query->verified();
                            },
                    ],
                    'rating'
                )
                ->orderByDesc(
                    'saved_shops.created_at'
                )
                ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'shops' =>
                    CustomerShopResource::collection(
                        $shops
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Check whether one shop is saved by the authenticated customer.
     */
    public function status(
        Request $request,
        string $shop
    ): JsonResponse {
        $visibleShop =
            Shop::query()
                ->visibleToCustomers()
                ->whereKey(
                    $shop
                )
                ->firstOrFail();

        $customerId =
            (int) $request
                ->user()
                ->getAuthIdentifier();

        $isSaved =
            DB::table(
                'customer_saved_shops'
            )
                ->where(
                    'customer_id',
                    $customerId
                )
                ->where(
                    'shop_id',
                    $visibleShop->id
                )
                ->exists();

        return response()->json([
            'success' => true,

            'data' => [
                'shop_id' =>
                    $visibleShop->id,

                'is_saved' =>
                    $isSaved,
            ],
        ]);
    }

    /**
     * Save one customer-visible shop.
     */
    public function store(
        Request $request,
        string $shop
    ): JsonResponse {
        $visibleShop =
            Shop::query()
                ->visibleToCustomers()
                ->whereKey(
                    $shop
                )
                ->firstOrFail();

        $customerId =
            (int) $request
                ->user()
                ->getAuthIdentifier();

        $alreadySaved =
            DB::table(
                'customer_saved_shops'
            )
                ->where(
                    'customer_id',
                    $customerId
                )
                ->where(
                    'shop_id',
                    $visibleShop->id
                )
                ->exists();

        if (! $alreadySaved) {
            $now =
                now();

            DB::table(
                'customer_saved_shops'
            )->insert([
                'customer_id' =>
                    $customerId,

                'shop_id' =>
                    $visibleShop->id,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ]);
        }

        return response()->json([
            'success' => true,

            'message' =>
                $alreadySaved
                    ? 'Shop is already saved.'
                    : 'Shop saved successfully.',

            'data' => [
                'shop_id' =>
                    $visibleShop->id,

                'is_saved' =>
                    true,
            ],
        ]);
    }

    /**
     * Remove one shop from the authenticated customer's saved shops.
     */
    public function destroy(
        Request $request,
        string $shop
    ): JsonResponse {
        $customerId =
            (int) $request
                ->user()
                ->getAuthIdentifier();

        DB::table(
            'customer_saved_shops'
        )
            ->where(
                'customer_id',
                $customerId
            )
            ->where(
                'shop_id',
                $shop
            )
            ->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Shop removed from saved shops.',

            'data' => [
                'shop_id' =>
                    (int) $shop,

                'is_saved' =>
                    false,
            ],
        ]);
    }
}