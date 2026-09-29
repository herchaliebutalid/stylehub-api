<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\GetShopOwnerAnalyticsRequest;
use App\Models\Shop;
use App\Services\Analytics\ShopOwnerAnalyticsService;
use Illuminate\Http\JsonResponse;

class ShopOwnerAnalyticsController extends Controller
{
    public function __construct(
        private readonly ShopOwnerAnalyticsService $analytics
    ) {}

    public function index(
        GetShopOwnerAnalyticsRequest $request
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        return response()->json([
            'success' =>
                true,

            'data' =>
                $this->analytics
                    ->overview(
                        shop:
                            $shop,

                        filters:
                            $request->validated(),
                    ),

            'generated_at' =>
                now()
                    ->toISOString(),
        ]);
    }

    private function authenticatedShop(
        GetShopOwnerAnalyticsRequest $request
    ): Shop {
        return $request
            ->user()
            ->shop()
            ->firstOrFail();
    }
}