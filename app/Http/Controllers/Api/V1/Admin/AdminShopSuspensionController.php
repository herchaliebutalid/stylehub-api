<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReactivateShopRequest;
use App\Http\Requests\Api\V1\Admin\SuspendShopRequest;
use App\Http\Resources\Api\V1\AdminShopSuspensionResource;
use App\Services\Admin\AdminShopSuspensionService;
use Illuminate\Http\JsonResponse;

class AdminShopSuspensionController extends Controller
{
    public function __construct(
        private readonly AdminShopSuspensionService $shopSuspension
    ) {}

    public function suspend(
        SuspendShopRequest $request,
        string $shop
    ): JsonResponse {
        $validated =
            $request->validated();

        $shopModel =
            $this->shopSuspension
                ->suspend(
                    shopId:
                        (int) $shop,

                    administrator:
                        $request->user(),

                    reason:
                        $validated['reason'],
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Shop suspended successfully.',

            'data' => [
                'shop' =>
                    (
                        new AdminShopSuspensionResource(
                            $shopModel
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    public function reactivate(
        ReactivateShopRequest $request,
        string $shop
    ): JsonResponse {
        $shopModel =
            $this->shopSuspension
                ->reactivate(
                    shopId:
                        (int) $shop,

                    administrator:
                        $request->user(),
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Shop reactivated successfully.',

            'data' => [
                'shop' =>
                    (
                        new AdminShopSuspensionResource(
                            $shopModel
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }
}