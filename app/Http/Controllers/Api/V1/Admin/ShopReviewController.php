<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RejectShopRequest;
use App\Http\Resources\Api\V1\AdminShopReviewResource;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ShopReviewController extends Controller
{
    /**
     * List shops awaiting administrator review.
     */
    public function pending(Request $request): JsonResponse
    {
        $shops = Shop::query()
            ->with('owner')
            ->where(
                'approval_status',
                ShopApprovalStatus::Pending->value
            )
            ->latest()
            ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'shops' => AdminShopReviewResource::collection(
                    $shops
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Approve a pending shop registration.
     */
    public function approve(
        Request $request,
        Shop $shop
    ): JsonResponse {
        $reviewedShop = DB::transaction(
            function () use ($request, $shop): Shop {
                $lockedShop = Shop::query()
                    ->whereKey($shop->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedShop->approval_status
                    !== ShopApprovalStatus::Pending
                ) {
                    abort(
                        Response::HTTP_CONFLICT,
                        'This shop registration has already been reviewed.'
                    );
                }

                $lockedShop->approval_status =
                    ShopApprovalStatus::Approved;

                /*
                 * Approval does not automatically open the shop.
                 * The owner will control operational availability later.
                 */
                $lockedShop->availability_status =
                    ShopAvailabilityStatus::Closed;

                $lockedShop->reviewed_by =
                    $request->user()->id;

                $lockedShop->reviewed_at = now();

                $lockedShop->rejection_reason = null;

                $lockedShop->save();

                return $lockedShop;
            }
        );

        $reviewedShop->load([
            'owner',
            'reviewer',
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Shop registration approved successfully.',

            'data' => [
                'shop' => (
                    new AdminShopReviewResource(
                        $reviewedShop
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Reject a pending shop registration.
     */
    public function reject(
        RejectShopRequest $request,
        Shop $shop
    ): JsonResponse {
        $validated = $request->validated();

        $reviewedShop = DB::transaction(
            function () use (
                $request,
                $shop,
                $validated
            ): Shop {
                $lockedShop = Shop::query()
                    ->whereKey($shop->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedShop->approval_status
                    !== ShopApprovalStatus::Pending
                ) {
                    abort(
                        Response::HTTP_CONFLICT,
                        'This shop registration has already been reviewed.'
                    );
                }

                $lockedShop->approval_status =
                    ShopApprovalStatus::Rejected;

                $lockedShop->availability_status =
                    ShopAvailabilityStatus::Closed;

                $lockedShop->reviewed_by =
                    $request->user()->id;

                $lockedShop->reviewed_at = now();

                $lockedShop->rejection_reason =
                    $validated['reason'];

                $lockedShop->save();

                return $lockedShop;
            }
        );

        $reviewedShop->load([
            'owner',
            'reviewer',
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Shop registration rejected successfully.',

            'data' => [
                'shop' => (
                    new AdminShopReviewResource(
                        $reviewedShop
                    )
                )->resolve($request),
            ],
        ]);
    }
}