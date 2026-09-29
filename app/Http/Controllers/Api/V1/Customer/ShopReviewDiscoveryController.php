<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\BrowseShopReviewsRequest;
use App\Http\Resources\Api\V1\CustomerPublicFeedbackResource;
use App\Models\Feedback;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;

class ShopReviewDiscoveryController extends Controller
{
    /**
     * List verified customer reviews for one customer-visible shop.
     */
    public function index(
        BrowseShopReviewsRequest $request,
        string $shop
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Customer-Visible Shop
        |--------------------------------------------------------------------------
        |
        | Pending/rejected/deleted shops and shops owned by suspended
        | accounts return 404.
        |
        */

        $visibleShop =
            Shop::query()
                ->visibleToCustomers()
                ->whereKey(
                    $shop
                )
                ->firstOrFail();

        $validated =
            $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Verified Review Base Query
        |--------------------------------------------------------------------------
        */

        $verifiedBase =
            Feedback::query()
                ->verified()
                ->where(
                    'shop_id',
                    $visibleShop->id
                );

        /*
        |--------------------------------------------------------------------------
        | Overall Rating Summary
        |--------------------------------------------------------------------------
        |
        | This remains the overall verified rating even when the review list
        | itself is filtered to a particular star value.
        |
        */

        $reviewCount =
            (clone $verifiedBase)
                ->count();

        $averageRating =
            (clone $verifiedBase)
                ->avg(
                    'rating'
                );

        /*
        |--------------------------------------------------------------------------
        | Review Listing
        |--------------------------------------------------------------------------
        */

        $reviewsQuery =
            Feedback::query()
                ->verified()
                ->where(
                    'shop_id',
                    $visibleShop->id
                )
                ->with([
                    'appointment',
                ]);

        if (
            isset(
                $validated['rating']
            )
        ) {
            $reviewsQuery->where(
                'rating',
                $validated['rating']
            );
        }

        $perPage =
            (int) (
                $validated['per_page']
                ?? 10
            );

        $reviews =
            $reviewsQuery
                ->orderByDesc(
                    'created_at'
                )
                ->paginate(
                    $perPage
                );

        return response()->json([
            'success' => true,

            'data' => [
                'shop' => [
                    'id' =>
                        $visibleShop->id,

                    'name' =>
                        $visibleShop->name,

                    'rating' => [
                        'average' =>
                            $averageRating === null
                                ? null
                                : round(
                                    (float) $averageRating,
                                    2
                                ),

                        'review_count' =>
                            $reviewCount,
                    ],
                ],

                'reviews' =>
                    CustomerPublicFeedbackResource::collection(
                        collect(
                            $reviews->items()
                        )
                    )->resolve($request),

                'pagination' => [
                    'current_page' =>
                        $reviews->currentPage(),

                    'per_page' =>
                        $reviews->perPage(),

                    'total' =>
                        $reviews->total(),

                    'last_page' =>
                        $reviews->lastPage(),

                    'has_more_pages' =>
                        $reviews->hasMorePages(),
                ],
            ],
        ]);
    }
}