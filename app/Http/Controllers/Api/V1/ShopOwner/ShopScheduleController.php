<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Enums\DayOfWeek;
use App\Enums\ShopAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\UpdateShopAvailabilityRequest;
use App\Http\Requests\Api\V1\ShopOwner\UpdateShopScheduleRequest;
use App\Http\Resources\Api\V1\ShopScheduleResource;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopScheduleController extends Controller
{
    /**
     * Return the authenticated owner's weekly operating schedule.
     */
    public function show(
        Request $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $schedules = $shop->schedules()
            ->orderBy('day_of_week')
            ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'availability' => [
                    'status' =>
                        $shop->availability_status->value,

                    'status_label' =>
                        $shop->availability_status->label(),
                ],

                'schedule' =>
                    ShopScheduleResource::collection(
                        $schedules
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Replace the authenticated owner's complete weekly schedule.
     *
     * All seven days are processed inside one transaction.
     */
    public function update(
        UpdateShopScheduleRequest $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $validated = $request->validated();

        $schedules = DB::transaction(
            function () use (
                $shop,
                $validated
            ) {
                /*
                 * Lock the shop so simultaneous schedule replacement
                 * requests for the same tenant are serialized.
                 */
                $lockedShop = Shop::query()
                    ->whereKey($shop->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                foreach (
                    $validated['schedule']
                    as $entry
                ) {
                    $day = DayOfWeek::from(
                        (int) $entry['day_of_week']
                    );

                    $isOpen = (bool) $entry['is_open'];

                    $lockedShop->schedules()
                        ->updateOrCreate(
                            [
                                'day_of_week' =>
                                    $day->value,
                            ],
                            [
                                'is_open' =>
                                    $isOpen,

                                'opens_at' =>
                                    $isOpen
                                        ? $entry['opens_at']
                                        : null,

                                'closes_at' =>
                                    $isOpen
                                        ? $entry['closes_at']
                                        : null,
                            ]
                        );
                }

                return $lockedShop->schedules()
                    ->orderBy('day_of_week')
                    ->get();
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Weekly shop schedule updated successfully.',

            'data' => [
                'schedule' =>
                    ShopScheduleResource::collection(
                        $schedules
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Update the shop's current operational availability.
     */
    public function updateAvailability(
        UpdateShopAvailabilityRequest $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $status =
            ShopAvailabilityStatus::from(
                $request->validated(
                    'status'
                )
            );

        $updatedShop = DB::transaction(
            function () use (
                $shop,
                $status
            ): Shop {
                $lockedShop = Shop::query()
                    ->whereKey($shop->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedShop->availability_status =
                    $status;

                $lockedShop->save();

                return $lockedShop;
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Shop availability updated successfully.',

            'data' => [
                'availability' => [
                    'status' =>
                        $updatedShop
                            ->availability_status
                            ->value,

                    'status_label' =>
                        $updatedShop
                            ->availability_status
                            ->label(),
                ],
            ],
        ]);
    }

    /**
     * Resolve the authenticated owner's shop.
     */
    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request->user()
            ->shop()
            ->firstOrFail();
    }
}